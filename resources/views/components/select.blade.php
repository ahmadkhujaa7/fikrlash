@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null])
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    @endif
    <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->except('class')->merge(['class' => 'field']) }}>
        @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)<p class="field-error">{{ $message }}</p>@enderror
</div>
