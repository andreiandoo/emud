<?php

namespace App\Livewire\Admin;

use App\Models\MediaAsset;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class MediaLibrary extends Component
{
    use WithFileUploads, WithPagination;

    public array $files = [];

    public function upload(): void
    {
        $this->validate(['files.*' => ['required', 'image', 'max:8192']]);
        foreach ($this->files as $file) {
            $path = $file->store('media', 'public');
            MediaAsset::create(['uploaded_by' => auth()->id(), 'disk' => 'public', 'path' => $path, 'filename' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize()]);
        }
        $this->reset('files');
    }

    public function delete(int $id): void
    {
        MediaAsset::findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.media-library', ['assets' => MediaAsset::latest()->paginate(30)]);
    }
}
