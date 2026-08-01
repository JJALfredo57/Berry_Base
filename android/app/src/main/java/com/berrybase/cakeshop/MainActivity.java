package com.berrybase.cakeshop;

import android.app.DownloadManager;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.URLUtil;
import android.webkit.WebView;
import android.widget.Toast;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    private static final String APP_ORIGIN = "https://berry-base-main.laravel.cloud";
    private static final String CUSTOM_SCHEME = "com.berrybase.cakeshop";
    private static final String ORDERS_CHANNEL_ID = "berry_orders";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        createNotificationChannel();

        WebView webView = getBridge().getWebView();
        if (webView == null) return;

        webView.addJavascriptInterface(new BerryBaseDownloadBridge(this), "BerryBaseDownloads");
        webView.setDownloadListener((url, userAgent, contentDisposition, mimeType, contentLength) -> {
            String filename = URLUtil.guessFileName(url, contentDisposition, mimeType);
            downloadUrl(url, filename, userAgent, mimeType);
        });
        handleIncomingIntent(getIntent());
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleIncomingIntent(intent);
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return;

        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager == null || manager.getNotificationChannel(ORDERS_CHANNEL_ID) != null) return;

        NotificationChannel channel = new NotificationChannel(
            ORDERS_CHANNEL_ID,
            "Berry Base Updates",
            NotificationManager.IMPORTANCE_HIGH
        );
        channel.setDescription("Order, payment, message, and delivery updates");
        channel.enableVibration(true);
        channel.setShowBadge(true);
        manager.createNotificationChannel(channel);
    }

    private void handleIncomingIntent(Intent intent) {
        if (intent == null || intent.getData() == null) return;

        Uri uri = intent.getData();
        String target = null;

        if (CUSTOM_SCHEME.equals(uri.getScheme())) {
            String path = uri.getPath();
            if (path == null || path.isEmpty()) path = "/";
            String query = uri.getQuery();
            target = APP_ORIGIN + path + (query != null && !query.isEmpty() ? "?" + query : "");
        } else if ("https".equals(uri.getScheme()) && "berry-base-main.laravel.cloud".equals(uri.getHost())) {
            target = uri.toString();
        }

        if (target == null) return;
        WebView webView = getBridge().getWebView();
        if (webView != null) webView.loadUrl(target);
    }

    private void downloadUrl(String url, String filename, String userAgent, String mimeType) {
        try {
            if (url == null || url.trim().isEmpty() || url.startsWith("blob:")) {
                Toast.makeText(this, "This file cannot be downloaded here.", Toast.LENGTH_SHORT).show();
                return;
            }

            String safeName = sanitizeFilename(filename);
            DownloadManager.Request request = new DownloadManager.Request(Uri.parse(url));
            request.setTitle(safeName);
            request.setDescription("Downloading delivery proof");
            request.setMimeType(mimeType);
            request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, safeName);
            request.allowScanningByMediaScanner();

            if (userAgent != null && !userAgent.isEmpty()) {
                request.addRequestHeader("User-Agent", userAgent);
            }

            String cookies = CookieManager.getInstance().getCookie(url);
            if (cookies != null && !cookies.isEmpty()) {
                request.addRequestHeader("Cookie", cookies);
            }

            DownloadManager manager = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
            if (manager == null) throw new IllegalStateException("Download manager unavailable");
            manager.enqueue(request);
            Toast.makeText(this, "Download started", Toast.LENGTH_SHORT).show();
        } catch (Exception e) {
            Toast.makeText(this, "Download failed. Please try again.", Toast.LENGTH_LONG).show();
        }
    }

    private String sanitizeFilename(String filename) {
        String fallback = "delivery-proof.jpg";
        String value = filename == null || filename.trim().isEmpty() ? fallback : filename.trim();
        value = value.replaceAll("[\\\\/:*?\"<>|]+", "-");
        if (!value.contains(".")) value += ".jpg";
        return value;
    }

    private class BerryBaseDownloadBridge {
        private final MainActivity activity;

        BerryBaseDownloadBridge(MainActivity activity) {
            this.activity = activity;
        }

        @JavascriptInterface
        public void download(String url, String filename) {
            activity.runOnUiThread(() -> activity.downloadUrl(url, filename, null, null));
        }
    }
}
