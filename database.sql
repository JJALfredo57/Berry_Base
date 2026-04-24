-- Add missing columns to delivery_zones
ALTER TABLE `delivery_zones` 
  ADD COLUMN `shop_id` VARCHAR(12) NULL AFTER `id`,
  ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `is_active`;

-- Set existing zones to the first approved shop (para hindi mawala ang data)
UPDATE `delivery_zones` d
JOIN `shops` s ON s.status = 'approved'
SET d.shop_id = s.id
WHERE d.shop_id IS NULL
LIMIT 1;