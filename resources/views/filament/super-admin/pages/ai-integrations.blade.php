<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex items-center justify-end gap-2">
                <x-filament::button type="submit">
                    Save
                </x-filament::button>
            </div>
        </form>

        <x-filament::section>
            <x-slot name="heading">Test a provider</x-slot>
            <x-slot name="description">
                Fires a one-sentence prompt and reports latency, tokens, and cost.
                Save your keys first — the test uses the currently-persisted values.
            </x-slot>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach(config('llm.providers', []) as $key => $cfg)
                    <x-filament::button
                        color="gray"
                        outlined
                        icon="heroicon-o-play"
                        wire:click="testProvider('{{ $key }}')"
                        wire:loading.attr="disabled"
                        wire:target="testProvider('{{ $key }}')"
                    >
                        Test {{ $cfg['label'] ?? $key }}
                    </x-filament::button>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
