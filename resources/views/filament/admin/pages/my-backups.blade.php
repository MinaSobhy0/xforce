<x-filament-panels::page>
    @if($this->getHeaderWidgets())
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columns="1"
        />
    @endif

    {{ $this->table }}
</x-filament-panels::page>
