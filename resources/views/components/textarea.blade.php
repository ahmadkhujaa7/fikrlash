@props(['name', 'label' => null, 'hint' => null, 'value' => null, 'rows' => 4])
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    @endif
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
              @error($name) aria-invalid="true" @enderror
              {{ $attributes->except('class')->merge(['class' => 'field resize-y']) }}>{{ old($name, $value) }}</textarea>
    @error($name)
        <p class="field-error">{{ $message }}</p>
    @else
        @if ($hint)<p class="field-hint">{{ $hint }}</p>@endif
    @enderror
</div>
