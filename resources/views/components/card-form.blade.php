<div {{ $attributes->merge(['class' => 'square-card-form']) }}>
    <div id="{{ $id }}"></div>
    <input type="hidden" name="{{ $name }}" id="{{ $id }}-token">
    <div id="{{ $id }}-errors" role="alert" aria-live="polite"></div>
</div>
@once
<script src="{{ $scriptUrl }}"></script>
@endonce
<script>
(async () => {
    const id = @js($id);
    const container = document.getElementById(id);
    const input = document.getElementById(id + '-token');
    const errors = document.getElementById(id + '-errors');
    const form = container.closest('form');

    if (!window.Square || !form) {
        errors.textContent = !form ? 'The card form must be inside a <form>.' : 'The Square Web Payments SDK failed to load.';
        return;
    }

    const payments = window.Square.payments(@js($applicationId), @js($locationId));
    const card = await payments.card();
    await card.attach('#' + id);

    form.addEventListener('submit', async (event) => {
        if (input.value) {
            return;
        }

        event.preventDefault();
        errors.textContent = '';

        try {
            const result = await card.tokenize(@js($verification) ?? undefined);

            if (result.status === 'OK') {
                input.value = result.token;
                form.submit();
            } else {
                errors.textContent = (result.errors || []).map((error) => error.message).join(' ') || 'The card could not be processed.';
            }
        } catch (error) {
            errors.textContent = error.message;
        }
    });
})();
</script>
