package com.jmmart.conversation_shortcuts;

import android.content.Context;
import android.content.Intent;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;

import androidx.annotation.NonNull;
import androidx.core.app.Person;
import androidx.core.content.pm.ShortcutInfoCompat;
import androidx.core.content.pm.ShortcutManagerCompat;
import androidx.core.graphics.drawable.IconCompat;

import io.flutter.embedding.engine.plugins.FlutterPlugin;
import io.flutter.plugin.common.MethodCall;
import io.flutter.plugin.common.MethodChannel;

/**
 * Registered in every Flutter engine, including the background one Firebase
 * Messaging starts while the app is closed, so a shortcut exists before the
 * chat notification that names it is posted.
 */
public class ConversationShortcutsPlugin implements FlutterPlugin, MethodChannel.MethodCallHandler {
    private MethodChannel channel;
    private Context context;

    @Override
    public void onAttachedToEngine(@NonNull FlutterPluginBinding binding) {
        context = binding.getApplicationContext();
        channel = new MethodChannel(binding.getBinaryMessenger(), "jmmart/conversation_shortcuts");
        channel.setMethodCallHandler(this);
    }

    @Override
    public void onDetachedFromEngine(@NonNull FlutterPluginBinding binding) {
        channel.setMethodCallHandler(null);
        channel = null;
    }

    @Override
    public void onMethodCall(@NonNull MethodCall call, @NonNull MethodChannel.Result result) {
        if (!"publish".equals(call.method)) {
            result.notImplemented();
            return;
        }

        try {
            String id = call.argument("id");
            String name = call.argument("name");
            byte[] avatar = call.argument("avatar");
            Bitmap bitmap = avatar == null ? null : BitmapFactory.decodeByteArray(avatar, 0, avatar.length);
            IconCompat icon = bitmap == null
                    ? IconCompat.createWithResource(context, context.getApplicationInfo().icon)
                    : IconCompat.createWithBitmap(bitmap);

            Person person = new Person.Builder().setKey(id).setName(name).setIcon(icon).setImportant(true).build();
            // Shortcut intents need an action; tapping it simply opens the app.
            Intent intent = context.getPackageManager().getLaunchIntentForPackage(context.getPackageName());
            if (intent == null) {
                intent = new Intent();
            }
            intent.setAction(Intent.ACTION_MAIN);

            ShortcutInfoCompat shortcut = new ShortcutInfoCompat.Builder(context, id)
                    .setShortLabel(name)
                    .setLongLived(true)
                    .setPerson(person)
                    .setIcon(icon)
                    .setIntent(intent)
                    .build();
            ShortcutManagerCompat.pushDynamicShortcut(context, shortcut);
            result.success(null);
        } catch (Exception error) {
            result.error("shortcut_failed", error.getMessage(), null);
        }
    }
}
