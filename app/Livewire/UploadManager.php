<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\PendingFile;
use Livewire\Attributes\On;

class UploadManager extends Component
{
    public bool $isVisible = false;
    public PendingFile|null $pending_file_to_item= null;
    public bool $is_sub = false;
    public $pending_files_count = 0;
    protected $listeners = [
        'openUploadManager' => 'openModal',
        'closeUploadManager' => 'closeModal',
    ];

    public function mount() {
        $this->countCompletedPendingFiles() ;
    }
    //#[On('pending-file-deleted')]
    public function countCompletedPendingFiles() {
        $this->pending_files_count =  PendingFile::completed()->byUser(auth()->id())->count() ;
    }
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
    public function openModalPendingFilesToItem(): void
    {
        $this->dispatch('open-modal', id: 'pending-files-to-item-modal');
        //$this->isVisible = true;
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', id: 'upload-manager-modal');
        $this->isVisible = false;
    }

    public function closeModalPendingFiles(): void
    {
        $this->dispatch('close-modal', id: 'pending-files-modal');
    }
    public function closeModalPendingFilesToItem(): void
    {
        $this->dispatch('close-modal', id: 'pending-files-to-item-modal');

        //$this->dispatch('open-modal', id: 'pending-files-modal');
    }


    #[On('pending-files-created')]
    public function actionPendingFileCreated() {
        $this->countCompletedPendingFiles() ;
        $this->dispatch('item-created');
    }

    #[On('pending-file-to-item-end')]
    public function actionPendingFileToItemEnd() {
        // Delete $this->pending_file_to_item
        $this->pending_file_to_item->delete();
        $this->pending_file_to_item = null;
        $this->closeModalPendingFilesToItem();
        $this->countCompletedPendingFiles() ;
        $this->openModalPendingFiles() ;
        $this->dispatch('pending-file-deleted');
    }

    #[On('actionPendingFileToItem')]
    public function actionPendingFileToItem($pendingFileId, $isSub = false) {
        $this->pending_file_to_item = PendingFile::find($pendingFileId);
        $this->is_sub = $isSub;
        $this->closeModalPendingFiles();
        $this->openModalPendingFilesToItem();
    }

    public function render()
    {
        return view('livewire.upload-manager');
    }
}
