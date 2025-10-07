<div>
    <form wire:submit.prevent="createItem">
        {{ $this->form }}
        <div class="flex justify-between mt-6">
            <div class="flex gap-3">
                <x-filament::button
                    type="submit"
                    color="success"
                >
                    Enregistrer
                </x-filament::button>
            </div>
        </div>
    </form>

    <x-filament-actions::modals />
</div>
