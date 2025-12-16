<x-filament-panels::page>
    <x-filament-panels::form wire:submit="submit">
        {{ $this->form }}

        <div class="flex justify-end gap-x-3">
            <x-filament::button type="submit">
                Sauvegarder
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
