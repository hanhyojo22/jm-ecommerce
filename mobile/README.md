# JMMart mobile (buyer)

Android-first Flutter buyer app for the existing JMMart Laravel backend. It talks
to the versioned JSON API at `/api/v1`, signs in through Clerk, and does not
duplicate any pricing, stock, order, return, or chat rules.

## What is here

- `lib/src/core` — Dio API client, stable error contract, Reverb WebSocket client.
- `lib/src/models` — typed JSON models for catalog, cart, checkout, orders, chat.
- `lib/src/providers` — Riverpod 3 providers and cart/notifier state.
- `lib/src/screens` — catalog, product, cart, checkout, orders, chat, addresses, account.
- `lib/src/widgets` — shared cards, async views, bottom-navigation shell.

## Requirements

- Flutter 3.47+ and Dart 3.13+
- Android SDK 36, build-tools 36.0.0, NDK 28.2.13676358
- JDK 21
- A Clerk publishable key
- The Laravel API running locally

## Run

Android emulator (the host is `10.0.2.2`):

```bash
flutter run \
  --dart-define=CLERK_PUBLISHABLE_KEY=pk_test_your_key \
  --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1 \
  --dart-define=REVERB_APP_KEY=jmmart-local-key \
  --dart-define=REVERB_HOST=10.0.2.2 \
  --dart-define=REVERB_PORT=8080
```

Physical device on the same network: replace both hosts with the computer's LAN
address (for example `192.168.1.20`) and make sure the Laravel server listens on
`0.0.0.0:8000`.

USB device alternative:

```bash
adb reverse tcp:8000 tcp:8000
flutter run \
  --dart-define=CLERK_PUBLISHABLE_KEY=pk_test_your_key \
  --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1 \
  --dart-define=REVERB_HOST=127.0.0.1
```

The app shows a setup screen instead of crashing when the Clerk key is missing.

## Verify

```bash
flutter analyze
flutter test
flutter build apk --debug
```

## Notes

- Clerk is initialized once in `lib/main.dart` before `runApp`, and its state is
  injected into the root Riverpod scope. This keeps the bearer token available
  to every route, including pushed detail screens.
- On a Clerk development instance, sign up or sign in with a
  `+clerk_test@example.com` address and use `424242` for the email or two-step
  code. Sign-in on a new emulator may ask for that second code.
- Cleartext HTTP is enabled in `AndroidManifest.xml` for local development. Use
  HTTPS and remove `android:usesCleartextTraffic` before a production release.
- Chat uses Reverb private channels through `POST /api/v1/broadcasting/auth`
  with the Clerk bearer token. If the socket cannot connect, the thread falls
  back to polling every 15 seconds.
- PayMongo checkout opens in the external browser. Passing an app-specific
  `success_url`/`cancel_url` is supported by the API and can be wired once the
  return deep link is defined.
