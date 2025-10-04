<?php

namespace App\Livewire;

use Livewire\Component;

class UploadManager extends Component
{
    public bool $isVisible = false;

    protected $listeners = [
        'openUploadManager' => 'openModal',
        'closeUploadManager' => 'closeModal',
    ];
    public function openModal(): void
    {
        $this->dispatch('open-modal', id: 'upload-manager-modal');
        $this->isVisible = true;
    }
    public function openModalPendingFiles(): void
    {
        $this->dispatch('open-modal', id: 'pending-files-modal');
        //$this->isVisible = true;
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', id: 'upload-manager-modal');
        $this->isVisible = false;
    }

    public function render()
    {
        return view('livewire.upload-manager');
    }
}
