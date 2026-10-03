@props(['name', 'label' => null, 'type' => 'text', 'hint' => null, 'value' => null])
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    @endif
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
           {{ $attributes->except('class')->merge(['class' => 'field']) }}>
    @error($name)
        <p id="{{ $name }}-error" class="field-error">{{ $message }}</p>
    @else
        @if ($hint)<p class="field-hint">{{ $hint }}</p>@endif
    @enderror
</div>
