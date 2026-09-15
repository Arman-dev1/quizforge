@props(['checkout'])

@php
    $transaction = $checkout->getTransaction();
    $customer = $checkout->getCustomer();
    $custom = $checkout->getCustomData();

    /*
     | Opening the overlay ourselves rather than relying on Paddle's
     | .paddle_button class scanning: that scan happens once at Initialize(),
     | so buttons rendered later by Livewire never get bound.
     |
     | Deliberately no successUrl. A hard redirect lands back here before the
     | webhook has recorded anything, so the page renders the OLD plan and
     | looks like the payment failed. Instead the overlay closes in place and
     | the page waits for the subscription to appear.
     */
    $options = array_filter([
        'transactionId' => $transaction['id'] ?? null,
        'items' => ($transaction['id'] ?? null) ? null : $checkout->getItems(),
        'customer' => $customer ? ['id' => $customer->paddle_id] : null,
        'customData' => $custom ?: null,
        'settings' => ['allowLogout' => false],
    ], fn ($value) => $value !== null);
@endphp

<button
    type="button"
    x-data="{
        opening: false,
        open() {
            if (typeof window.Paddle === 'undefined' || ! window.__qfPaddleReady) {
                // Nothing useful happens on click otherwise, so say so.
                alert(@js(__('The payment window could not load. Please disable any ad blocker and refresh the page.')));
                return;
            }

            this.opening = true;

            try {
                window.Paddle.Checkout.open(@js($options));
            } catch (error) {
                console.error('Paddle checkout failed to open', error);
                alert(@js(__('We could not open the payment window. Please try again.')));
            } finally {
                this.opening = false;
            }
        },
    }"
    x-on:click="open()"
    x-bind:disabled="opening"
    {{ $attributes->merge(['class' => 'disabled:opacity-70']) }}
>
    {{ $slot }}
</button>
