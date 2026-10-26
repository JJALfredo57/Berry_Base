<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OrderHistoryPurgeService
{
    private const ORDER_CHILD_TABLES = [
        'order_addons',
        'order_tracking',
        'order_reviews',
        'custom_orders',
        'kitchen_tickets',
        'messages',
        'notifications',
        'mobile_notifications',
        'payment_transactions',
        'customer_payment_qrs',
        'order_discounts',
        'loyalty_transactions',
        'order_items',
        'voucher_redemptions',
    ];

    private const FINANCIAL_COUNT_KEYS = [
        'seller_payout_ledgers',
        'seller_payout_items',
        'seller_payouts',
        'rider_remittances',
        'rider_remittance_batches',
        'order_refunds',
        'uploaded_receipt_files',
    ];

    public function preview(): array
    {
        $orders = DB::table('orders')->select('id', 'track_code')->get();
        $orderIds = $orders->pluck('id')->all();
        $trackCodes = $orders->pluck('track_code')->filter()->unique()->values()->all();
        $customOrderIds = Schema::hasTable('custom_orders') && $orderIds
            ? DB::table('custom_orders')->whereIn('order_id', $orderIds)->pluck('id')->all()
            : [];

        $counts = ['orders' => count($orderIds)];
        foreach (self::ORDER_CHILD_TABLES as $table) {
            $counts[$table] = $this->countOrderChildren($table, $orderIds, $trackCodes);
        }

        $counts['order_requests'] = $this->countConvertedRequests($orderIds, $customOrderIds);
        $counts['product_daily_orders'] = Schema::hasTable('product_daily_orders')
            ? DB::table('product_daily_orders')->count()
            : 0;
        $messageIds = Schema::hasTable('messages') && $orderIds
            ? DB::table('messages')->whereIn('order_id', $orderIds)->pluck('id')->all()
            : [];
        $counts['message_reactions'] = Schema::hasTable('message_reactions') && $messageIds
            ? DB::table('message_reactions')->whereIn('message_id', $messageIds)->count()
            : 0;

        $financialPlan = $this->financialPurgePlan($orderIds);
        $counts = array_merge($counts, $financialPlan['counts']);
        $financialRows = array_filter(
            array_intersect_key($counts, array_flip(self::FINANCIAL_COUNT_KEYS)),
            fn (int $count): bool => $count > 0
        );
        $snapshotOrderIds = $orderIds;
        $snapshotTrackCodes = $trackCodes;
        $snapshotCustomOrderIds = $customOrderIds;
        $snapshotFinancialIds = $financialPlan['ids'];
        sort($snapshotOrderIds, SORT_REGULAR);
        sort($snapshotTrackCodes, SORT_REGULAR);
        sort($snapshotCustomOrderIds, SORT_REGULAR);
        foreach ($snapshotFinancialIds as &$ids) {
            sort($ids, SORT_REGULAR);
        }
        unset($ids);

        $snapshotData = [
            'counts' => $counts,
            'order_ids' => $snapshotOrderIds,
            'track_codes' => $snapshotTrackCodes,
            'custom_order_ids' => $snapshotCustomOrderIds,
            'financial_ids' => $snapshotFinancialIds,
            'financial_blockers' => $financialPlan['blockers'],
            'receipt_paths' => $financialPlan['receipt_paths'],
        ];

        return [
            'counts' => $counts,
            'financial_rows' => $financialRows,
            'financial_blockers' => $financialPlan['blockers'],
            'financial_ids' => $financialPlan['ids'],
            'receipt_paths' => $financialPlan['receipt_paths'],
            'snapshot' => hash('sha256', json_encode($snapshotData, JSON_THROW_ON_ERROR)),
            'custom_order_ids' => $customOrderIds,
            'order_ids' => $orderIds,
            'track_codes' => $trackCodes,
        ];
    }

    public function purge(?string $expectedSnapshot = null): array
    {
        $result = DB::transaction(function () use ($expectedSnapshot): array {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $tables = array_values(array_filter(array_unique(array_merge(
                    ['orders', 'order_requests', 'product_daily_orders', 'message_reactions'],
                    self::ORDER_CHILD_TABLES,
                    self::FINANCIAL_COUNT_KEYS
                )), fn (string $table): bool => Schema::hasTable($table)));
                $quotedTables = implode(', ', array_map(
                    fn (string $table): string => '"' . str_replace('"', '""', $table) . '"',
                    $tables
                ));
                DB::statement("LOCK TABLE {$quotedTables} IN EXCLUSIVE MODE");
            }

            $orders = DB::table('orders')->lockForUpdate()->select('id', 'track_code')->get();
            $orderIds = $orders->pluck('id')->all();
            $trackCodes = $orders->pluck('track_code')->filter()->unique()->values()->all();
            $customOrderIds = Schema::hasTable('custom_orders') && $orderIds
                ? DB::table('custom_orders')->whereIn('order_id', $orderIds)->pluck('id')->all()
                : [];

            $preview = $this->preview();
            if ($expectedSnapshot !== null && !hash_equals($expectedSnapshot, $preview['snapshot'])) {
                throw new RuntimeException(
                    'The reviewed order and financial records changed. Run a new dry run before deleting anything.'
                );
            }
            if ($preview['financial_blockers']) {
                throw new RuntimeException(
                    'Some financial records are shared with data outside this purge scope. No data was deleted.'
                );
            }

            $counts = $preview['counts'];
            if (!$orderIds) {
                if (Schema::hasTable('product_daily_orders')) {
                    DB::table('product_daily_orders')->delete();
                }
                return ['counts' => $counts, 'receipt_paths' => []];
            }

            $messageIds = Schema::hasTable('messages')
                ? DB::table('messages')->whereIn('order_id', $orderIds)->pluck('id')->all()
                : [];
            if ($messageIds && Schema::hasTable('message_reactions')) {
                DB::table('message_reactions')->whereIn('message_id', $messageIds)->delete();
            }

            $financialIds = $preview['financial_ids'];
            if ($financialIds['seller_payout_items'] && Schema::hasTable('seller_payout_items')) {
                DB::table('seller_payout_items')->whereIn('id', $financialIds['seller_payout_items'])->delete();
            }
            if ($financialIds['seller_payout_ledgers'] && Schema::hasTable('seller_payout_ledgers')) {
                DB::table('seller_payout_ledgers')->whereIn('id', $financialIds['seller_payout_ledgers'])->delete();
            }
            if ($financialIds['rider_remittances'] && Schema::hasTable('rider_remittances')) {
                DB::table('rider_remittances')->whereIn('id', $financialIds['rider_remittances'])->delete();
            }
            if ($financialIds['order_refunds'] && Schema::hasTable('order_refunds')) {
                DB::table('order_refunds')->whereIn('id', $financialIds['order_refunds'])->delete();
            }
            if ($financialIds['seller_payouts'] && Schema::hasTable('seller_payouts')) {
                DB::table('seller_payouts')->whereIn('id', $financialIds['seller_payouts'])->delete();
            }
            if ($financialIds['rider_remittance_batches'] && Schema::hasTable('rider_remittance_batches')) {
                DB::table('rider_remittance_batches')->whereIn('id', $financialIds['rider_remittance_batches'])->delete();
            }

            foreach (self::ORDER_CHILD_TABLES as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                if ($table === 'customer_payment_qrs') {
                    if ($trackCodes && Schema::hasColumn($table, 'track_code')) {
                        DB::table($table)->whereIn('track_code', $trackCodes)->delete();
                    }
                    continue;
                }

                if (Schema::hasColumn($table, 'order_id')) {
                    DB::table($table)->whereIn('order_id', $orderIds)->delete();
                }
            }

            if (Schema::hasTable('order_requests')) {
                $query = DB::table('order_requests');
                $hasConvertedOrderId = Schema::hasColumn('order_requests', 'converted_order_id');
                $hasCustomOrderId = Schema::hasColumn('order_requests', 'custom_order_id');
                if ($hasConvertedOrderId && $hasCustomOrderId && $customOrderIds) {
                    $query->whereIn('converted_order_id', $orderIds)
                        ->orWhereIn('custom_order_id', $customOrderIds);
                } elseif ($hasConvertedOrderId) {
                    $query->whereIn('converted_order_id', $orderIds);
                } elseif ($hasCustomOrderId && $customOrderIds) {
                    $query->whereIn('custom_order_id', $customOrderIds);
                } else {
                    $query = null;
                }
                $query?->delete();
            }

            if (Schema::hasTable('product_daily_orders')) {
                DB::table('product_daily_orders')->delete();
            }

            DB::table('orders')->whereIn('id', $orderIds)->delete();

            return ['counts' => $counts, 'receipt_paths' => $preview['receipt_paths']];
        });

        $this->deleteReceiptFiles($result['receipt_paths']);

        return $result['counts'];
    }

    private function financialPurgePlan(array $orderIds): array
    {
        $ids = [
            'seller_payout_ledgers' => [],
            'seller_payout_items' => [],
            'seller_payouts' => [],
            'rider_remittances' => [],
            'rider_remittance_batches' => [],
            'order_refunds' => [],
        ];
        $receiptValues = [];
        $blockers = [];
        $counts = array_fill_keys(self::FINANCIAL_COUNT_KEYS, 0);

        $refundRows = collect();
        if (Schema::hasTable('order_refunds') && $orderIds) {
            $this->requireColumns('order_refunds', ['id', 'order_id', 'payout_ledger_id']);
            $refundColumns = ['id', 'payout_ledger_id'];
            if (Schema::hasColumn('order_refunds', 'receipt_path')) {
                $refundColumns[] = 'receipt_path';
            }
            $refundRows = DB::table('order_refunds')
                ->whereIn('order_id', $orderIds)
                ->get($refundColumns);
            $ids['order_refunds'] = $refundRows->pluck('id')->all();
            if (in_array('receipt_path', $refundColumns, true)) {
                $receiptValues = array_merge($receiptValues, $refundRows->pluck('receipt_path')->all());
            }
        }

        $refundLedgerIds = $refundRows->pluck('payout_ledger_id')->filter()->unique()->values()->all();
        $ledgerRows = collect();
        if (Schema::hasTable('seller_payout_ledgers')) {
            $this->requireColumns('seller_payout_ledgers', ['id', 'order_id', 'payout_id']);
            $ledgerRows = $orderIds
                ? DB::table('seller_payout_ledgers')->whereIn('order_id', $orderIds)->get(['id', 'order_id', 'payout_id'])
                : collect();
            if ($refundLedgerIds) {
                $ledgerRows = $ledgerRows->merge(
                    DB::table('seller_payout_ledgers')->whereIn('id', $refundLedgerIds)->get(['id', 'order_id', 'payout_id'])
                )->unique('id')->values();
            }
            $ids['seller_payout_ledgers'] = $ledgerRows->pluck('id')->all();
        } elseif ($refundLedgerIds) {
            $blockers['refund_payout_ledgers_without_ledger_table'] = count($refundLedgerIds);
        }

        $ledgerIds = $ledgerRows->pluck('id')->all();
        $payoutItemRows = collect();
        if (Schema::hasTable('seller_payout_items')) {
            $this->requireColumns('seller_payout_items', ['id', 'payout_id', 'ledger_id', 'order_id']);
            if ($orderIds || $ledgerIds) {
                $payoutItemRows = DB::table('seller_payout_items')
                    ->where(function ($query) use ($orderIds, $ledgerIds) {
                        if ($orderIds) {
                            $query->whereIn('order_id', $orderIds);
                        }
                        if ($ledgerIds) {
                            $orderIds
                                ? $query->orWhereIn('ledger_id', $ledgerIds)
                                : $query->whereIn('ledger_id', $ledgerIds);
                        }
                    })
                    ->get(['id', 'payout_id', 'ledger_id']);
            }
            $ids['seller_payout_items'] = $payoutItemRows->pluck('id')->all();
        }

        $itemLedgerIds = $payoutItemRows->pluck('ledger_id')->filter()->unique()->values()->all();
        $unscopedItemLedgerIds = array_values(array_diff($itemLedgerIds, $ledgerIds));
        if ($unscopedItemLedgerIds) {
            $blockers['seller_payout_items_with_unscoped_ledgers'] = count($unscopedItemLedgerIds);
        }

        $payoutIds = $ledgerRows->pluck('payout_id')
            ->merge($payoutItemRows->pluck('payout_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $payoutIdsToDelete = [];
        if ($payoutIds && Schema::hasTable('seller_payouts')) {
            $this->requireColumns('seller_payouts', ['id']);
            foreach ($payoutIds as $payoutId) {
                $hasOtherItems = Schema::hasTable('seller_payout_items')
                    && DB::table('seller_payout_items')
                        ->where('payout_id', $payoutId)
                        ->when($ids['seller_payout_items'], fn ($query) => $query->whereNotIn('id', $ids['seller_payout_items']))
                        ->exists();
                $hasOtherLedgers = Schema::hasTable('seller_payout_ledgers')
                    && DB::table('seller_payout_ledgers')
                        ->where('payout_id', $payoutId)
                        ->when($ids['seller_payout_ledgers'], fn ($query) => $query->whereNotIn('id', $ids['seller_payout_ledgers']))
                        ->exists();
                if ($hasOtherItems || $hasOtherLedgers) {
                    $blockers['seller_payouts_shared_with_unscoped_records'] =
                        ($blockers['seller_payouts_shared_with_unscoped_records'] ?? 0) + 1;
                } else {
                    $payoutIdsToDelete[] = $payoutId;
                }
            }
        } elseif ($payoutIds) {
            $blockers['payout_groups_without_payout_table'] = count($payoutIds);
        }
        $ids['seller_payouts'] = $payoutIdsToDelete;
        if ($payoutIdsToDelete && Schema::hasColumn('seller_payouts', 'payout_receipt_path')) {
            $receiptValues = array_merge(
                $receiptValues,
                DB::table('seller_payouts')
                    ->whereIn('id', $payoutIdsToDelete)
                    ->pluck('payout_receipt_path')
                    ->all()
            );
        }

        $remittanceRows = collect();
        if (Schema::hasTable('rider_remittances') && $orderIds) {
            $this->requireColumns('rider_remittances', ['id', 'order_id']);
            $remittanceColumns = ['id', 'order_id'];
            $hasBatchId = Schema::hasColumn('rider_remittances', 'batch_id');
            if ($hasBatchId) {
                $remittanceColumns[] = 'batch_id';
            }
            $hasReceiptPath = Schema::hasColumn('rider_remittances', 'receipt_path');
            if ($hasReceiptPath) {
                $remittanceColumns[] = 'receipt_path';
            }
            $remittanceRows = DB::table('rider_remittances')
                ->whereIn('order_id', $orderIds)
                ->get($remittanceColumns);
            $ids['rider_remittances'] = $remittanceRows->pluck('id')->all();
            if ($hasReceiptPath) {
                $receiptValues = array_merge($receiptValues, $remittanceRows->pluck('receipt_path')->all());
            }

            if ($hasBatchId && Schema::hasTable('rider_remittance_batches')) {
                $this->requireColumns('rider_remittance_batches', ['id']);
                foreach ($remittanceRows->pluck('batch_id')->filter()->unique() as $batchId) {
                    $hasOtherRemittances = DB::table('rider_remittances')
                        ->where('batch_id', $batchId)
                        ->whereNotIn('id', $ids['rider_remittances'])
                        ->exists();
                    if ($hasOtherRemittances) {
                        $blockers['rider_remittance_batches_shared_with_unscoped_records'] =
                            ($blockers['rider_remittance_batches_shared_with_unscoped_records'] ?? 0) + 1;
                    } else {
                        $ids['rider_remittance_batches'][] = $batchId;
                    }
                }
            } elseif ($hasBatchId && $remittanceRows->contains(fn ($row) => $row->batch_id !== null)) {
                $blockers['rider_remittance_batches_without_batch_table'] =
                    $remittanceRows->whereNotNull('batch_id')->count();
            }
        }

        $counts['seller_payout_ledgers'] = count($ids['seller_payout_ledgers']);
        $counts['seller_payout_items'] = count($ids['seller_payout_items']);
        $counts['seller_payouts'] = count($ids['seller_payouts']);
        $counts['rider_remittances'] = count($ids['rider_remittances']);
        $counts['rider_remittance_batches'] = count($ids['rider_remittance_batches']);
        $counts['order_refunds'] = count($ids['order_refunds']);
        $receiptPaths = [];
        foreach (array_unique(array_filter($receiptValues)) as $receiptValue) {
            $path = $this->receiptStoragePath((string) $receiptValue);
            if ($path === null) {
                $blockers['unmanaged_receipt_files'] =
                    ($blockers['unmanaged_receipt_files'] ?? 0) + 1;
            } else {
                $receiptPaths[] = $path;
            }
        }
        $receiptPaths = array_values(array_unique($receiptPaths));
        $counts['uploaded_receipt_files'] = count($receiptPaths);

        return [
            'ids' => $ids,
            'blockers' => $blockers,
            'counts' => $counts,
            'receipt_paths' => $receiptPaths,
        ];
    }

    private function receiptStoragePath(string $urlOrPath): ?string
    {
        $path = parse_url(trim($urlOrPath), PHP_URL_PATH) ?: $urlOrPath;
        $path = ltrim(str_replace('\\', '/', rawurldecode($path)), '/');
        $diskNames = array_values(array_unique(array_filter([
            config('filesystems.upload_disk', 'public'),
            'public',
        ])));

        foreach ($diskNames as $diskName) {
            $diskUrl = config("filesystems.disks.{$diskName}.url");
            $basePath = $diskUrl ? ltrim((string) parse_url($diskUrl, PHP_URL_PATH), '/') : '';
            if ($basePath !== '' && str_starts_with($path, rtrim($basePath, '/') . '/')) {
                $path = substr($path, strlen(rtrim($basePath, '/')) + 1);
                break;
            }
        }

        foreach ($diskNames as $diskName) {
            $bucket = config("filesystems.disks.{$diskName}.bucket");
            if (!$bucket) {
                continue;
            }

            foreach ([
                'storage/v1/object/public/' . trim((string) $bucket, '/') . '/',
                'storage/v1/object/sign/' . trim((string) $bucket, '/') . '/',
                trim((string) $bucket, '/') . '/uploads/',
            ] as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $path = substr($path, strlen($prefix));
                    if ($prefix === trim((string) $bucket, '/') . '/uploads/') {
                        $path = 'uploads/' . $path;
                    }
                    break 2;
                }
            }
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        foreach ([
            'uploads/payout_receipts/',
            'uploads/refund_receipts/',
            'uploads/rider_remittances/',
            'uploads/delivery/',
        ] as $prefix) {
            if (str_starts_with($path, $prefix) && !str_contains($path, '..')) {
                return $path;
            }
        }

        return null;
    }

    private function deleteReceiptFiles(array $paths): void
    {
        $diskNames = array_values(array_unique(array_filter([
            config('filesystems.upload_disk', 'public'),
            'public',
        ])));
        foreach ($paths as $path) {
            foreach ($diskNames as $diskName) {
                try {
                    $disk = Storage::disk($diskName);
                    if ($disk->exists($path) && !$disk->delete($path)) {
                        throw new RuntimeException("Storage returned false while deleting from [{$diskName}].");
                    }
                } catch (\Throwable $exception) {
                    Log::error('Order history purge could not delete a linked receipt file.', [
                        'disk' => $diskName,
                        'path' => $path,
                        'message' => $exception->getMessage(),
                    ]);
                    throw new RuntimeException(
                        'Database records were deleted, but linked receipt-file cleanup failed. Check storage and logs.',
                        0,
                        $exception
                    );
                }
            }
        }
    }

    private function requireColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                throw new RuntimeException("Cannot safely purge financial records: {$table}.{$column} is missing.");
            }
        }
    }

    private function countOrderChildren(string $table, array $orderIds, array $trackCodes): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        if ($table === 'customer_payment_qrs') {
            return $trackCodes && Schema::hasColumn($table, 'track_code')
                ? DB::table($table)->whereIn('track_code', $trackCodes)->count()
                : 0;
        }

        return $orderIds && Schema::hasColumn($table, 'order_id')
            ? DB::table($table)->whereIn('order_id', $orderIds)->count()
            : 0;
    }

    private function countConvertedRequests(array $orderIds, array $customOrderIds): int
    {
        if (!Schema::hasTable('order_requests')) {
            return 0;
        }

        $hasConvertedOrderId = Schema::hasColumn('order_requests', 'converted_order_id');
        $hasCustomOrderId = Schema::hasColumn('order_requests', 'custom_order_id');
        if ($hasConvertedOrderId && $hasCustomOrderId && $customOrderIds) {
            return DB::table('order_requests')
                ->whereIn('converted_order_id', $orderIds)
                ->orWhereIn('custom_order_id', $customOrderIds)
                ->count();
        }
        if ($hasConvertedOrderId && $orderIds) {
            return DB::table('order_requests')->whereIn('converted_order_id', $orderIds)->count();
        }
        if ($hasCustomOrderId && $customOrderIds) {
            return DB::table('order_requests')->whereIn('custom_order_id', $customOrderIds)->count();
        }

        return 0;
    }
}
