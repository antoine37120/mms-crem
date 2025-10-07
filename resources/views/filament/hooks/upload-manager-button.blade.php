@if(auth()->user()?->id)
{{-- Composant Upload Manager en bas à droite --}}
    @livewire('upload-manager')
@endif
