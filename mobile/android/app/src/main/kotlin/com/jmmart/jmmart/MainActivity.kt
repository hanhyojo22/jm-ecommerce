package com.jmmart.jmmart

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import io.flutter.plugin.common.StandardMethodCodec

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        val messenger = flutterEngine.dartExecutor.binaryMessenger
        MethodChannel(
            messenger,
            "jmmart/welcome_promo",
            StandardMethodCodec.INSTANCE,
            messenger.makeBackgroundTaskQueue(),
        ).setMethodCallHandler { call, result ->
            if (call.method != "claimWelcomePromo") {
                result.notImplemented()
                return@setMethodCallHandler
            }
            val preferences = getSharedPreferences("welcome_promo", MODE_PRIVATE)
            if (preferences.getBoolean("shown", false)) {
                result.success(false)
            } else {
                result.success(preferences.edit().putBoolean("shown", true).commit())
            }
        }
    }
}
