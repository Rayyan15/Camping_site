{{--
    One labelled input with its hint and error wired by id, so screen readers announce both.
    Props: id, name, label, type, value, autocomplete, hint, required, inputmode, maxlength.
--}}
@php
    $type = $type ?? 'text';
    $value = $value ?? '';
    $required = $required ?? true;
    $hintId = isset($hint) ? $id.'-hint' : null;
    $errorId = $errors->has($name) ? $id.'-error' : null;
    $describedBy = trim(($hintId ?? '').' '.($errorId ?? ''));
@endphp
<div>
    <label for="{{ $id }}" class="field-label">{{ $label }}@unless($required) <span class="font-medium normal-case tracking-normal">(opsional)</span>@endunless</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           @if($type !== 'password') value="{{ $value }}" @endif
           @if(! empty($autocomplete)) autocomplete="{{ $autocomplete }}" @endif
           @if(! empty($inputmode)) inputmode="{{ $inputmode }}" @endif
           @if(! empty($maxlength)) maxlength="{{ $maxlength }}" @endif
           @if($required) required @endif
           @if($errorId) aria-invalid="true" @endif
           @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
           class="field-input">
    @isset($hint)
        <p id="{{ $hintId }}" class="mt-1 text-sm text-ink-soft">{{ $hint }}</p>
    @endisset
    @error($name)
        <p id="{{ $errorId }}" class="mt-1 text-sm font-semibold text-ember-dark">{{ $message }}</p>
    @enderror
</div>
