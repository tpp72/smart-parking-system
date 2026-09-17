@props(['name'])

<div role="tabpanel" tabindex="0"
    x-show="active === @js((string) $name)"
    x-bind:id="$root.dataset.tabsId + '-panel-' + @js((string) $name)"
    x-bind:aria-labelledby="$root.dataset.tabsId + '-tab-' + @js((string) $name)"
    {{ $attributes->merge(['class' => 'py-4 focus-visible:outline-offset-4']) }}>
    {{ $slot }}
</div>
