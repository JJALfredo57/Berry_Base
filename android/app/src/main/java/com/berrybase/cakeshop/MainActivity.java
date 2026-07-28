package com.berrybase.cakeshop;

import android.app.DownloadManager;
import android.content.Context;
import android.net.Uri;
import android.os.Bundle;
import android.os.Environment;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.URLUtil;
import android.webkit.WebView;
import android.widget.Toast;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        WebView webView = getBridge().getWebView();
        if (webView == null) return;

        webView.addJavascriptInterface(new BerryBaseDownloadBridge(this), "BerryBaseDownloads");
        webView.setDownloadListener((url, userAgent, contentDisposition, mimeType, contentLength) -> {
            String filename = URLUtil.guessFileName(url, contentDisposition, mimeType);
            downloadUrl(url, filename, userAgent, mimeType);
        });
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
