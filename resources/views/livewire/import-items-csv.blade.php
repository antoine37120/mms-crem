<div>
    {{ $this->form }}
    
    @if(count($parsedCsvRows) > 0)
        <div class="mt-8">
            <h3 class="text-lg font-medium mb-4">Aperçu de l'import</h3>
            {{ $this->table }}
        </div>
    @endif
    
    <x-filament-actions::modals />
</div>
