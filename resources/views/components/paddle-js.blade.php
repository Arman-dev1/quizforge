@php
    use App\Models\PaymentSetting;

    $settings = PaymentSetting::current();
    $token = config('cashier.client_side_token');
    $active = $settings->usesPaddle() && $settings->isConfigured() && filled($token);
@endphp

@if ($active)
    {{--
      | Paddle.js, loaded once for the whole app.
      |
      | Cashier's @paddleJS emits the CDN <script> and an inline
      | Paddle.Initialize() together in the page body. That breaks under
      | wire:navigate: Livewire swaps the body and re-runs inline scripts,
      | but a <script src> inserted that way does not execute synchronously,
      | so the inline code runs first and throws "Paddle is not defined".
      |
      | Loading it in <head> with data-navigate-once means it is fetched
      | exactly once per full page load and survives every SPA navigation.
    --}}
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js" data-navigate-once></script>

    <script data-navigate-once>
        (function () {
            // Guard against double-initialising across navigations.
            if (window.__qfPaddleReady) return;

            function initPaddle() {
                if (typeof window.Paddle === 'undefined') return false;

                try {
                    @if (config('cashier.sandbox'))
                        window.Paddle.Environment.set('sandbox');
                    @endif

                    window.Paddle.Initialize({
                        token: @js($token),
                        /*
                         | The overlay closing is the only signal the page
                         | gets that a payment went through — the webhook
                         | that actually records it arrives separately, and
                         | later. Re-broadcast it so the billing page can
                         | wait for the subscription instead of sitting on
                         | stale "Free plan" state until someone reloads.
                         */
                        eventCallback: function (event) {
                            if (event.name === 'checkout.completed') {
                                window.dispatchEvent(new CustomEvent('paddle-checkout-completed', {
                                    detail: { transactionId: event.data?.transaction_id ?? null },
                                }));
                            }
                        },
                    });

                    window.__qfPaddleReady = true;
                } catch (error) {
                    console.error('Paddle failed to initialise', error);
                }

                return true;
            }

            // The CDN script above is synchronous, so this normally succeeds
            // immediately; the retry covers a slow or blocked network.
            if (! initPaddle()) {
                let attempts = 0;
                const timer = setInterval(function () {
                    if (initPaddle() || ++attempts > 40) clearInterval(timer);
                }, 250);
            }
        })();
    </script>
@endif
