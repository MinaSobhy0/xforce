<?php

namespace Modules\FaceChart\Livewire;

use Livewire\Component;

class FaceChartViewer extends Component
{
    public int $patientId;
    public ?int $appointmentId = null;
    public bool $editMode = false;

    public function mount(int $patientId, ?int $appointmentId = null, bool $editMode = false): void
    {
        $this->patientId = $patientId;
        $this->appointmentId = $appointmentId;
        $this->editMode = $editMode;
    }

    public function render()
    {
        return view('face_chart::livewire.face-chart-viewer');
    }
}
