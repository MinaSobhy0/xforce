<?php

namespace Modules\KnowledgeBase\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Modules\KnowledgeBase\Services\HelpService;
use Modules\KnowledgeBase\Models\HelpUserProgress;

class ScreenGuide extends Component
{
    public bool $isActive = false;
    public array $guide = [];
    public int $currentStep = 0;
    public int $totalSteps = 0;
    public string $screenKey = '';
    public string $panel = 'tenant';

    protected HelpService $helpService;

    public function boot(HelpService $helpService): void
    {
        $this->helpService = $helpService;
    }

    #[On('start-guide')]
    public function startGuide(string $screenKey, string $panel = 'tenant'): void
    {
        $this->screenKey = $screenKey;
        $this->panel = $panel;

        $locale = app()->getLocale();
        $this->guide = $this->helpService->getGuideForScreen($screenKey, $panel, $locale) ?? [];

        if (empty($this->guide)) {
            return;
        }

        $this->totalSteps = count($this->guide['steps'] ?? []);
        $this->currentStep = 0;
        $this->isActive = true;

        // Track progress if user is authenticated
        if (auth()->check()) {
            HelpUserProgress::startGuide(
                auth()->id(),
                $this->guide['id'],
                $screenKey,
                $this->totalSteps
            );
        }

        $this->dispatch('guide-started', guide: $this->guide);
    }

    public function nextStep(): void
    {
        if ($this->currentStep < $this->totalSteps - 1) {
            $this->currentStep++;
            $this->updateProgress();
            $this->dispatch('guide-step-changed', step: $this->currentStep);
        } else {
            $this->completeGuide();
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 0) {
            $this->currentStep--;
            $this->dispatch('guide-step-changed', step: $this->currentStep);
        }
    }

    public function goToStep(int $step): void
    {
        if ($step >= 0 && $step < $this->totalSteps) {
            $this->currentStep = $step;
            $this->updateProgress();
            $this->dispatch('guide-step-changed', step: $this->currentStep);
        }
    }

    public function skipGuide(): void
    {
        // Mark as skipped
        if (auth()->check() && !empty($this->guide['id'])) {
            $progress = HelpUserProgress::where('user_id', auth()->id())
                ->where('guide_id', $this->guide['id'])
                ->first();

            if ($progress) {
                $progress->markSkipped();
            }
        }

        $this->closeGuide();
        $this->dispatch('guide-skipped');
    }

    public function completeGuide(): void
    {
        // Mark as completed
        if (auth()->check() && !empty($this->guide['id'])) {
            $progress = HelpUserProgress::where('user_id', auth()->id())
                ->where('guide_id', $this->guide['id'])
                ->first();

            if ($progress) {
                $progress->markCompleted();
            }
        }

        $this->closeGuide();
        $this->dispatch('guide-completed');
    }

    protected function closeGuide(): void
    {
        $this->isActive = false;
        $this->guide = [];
        $this->currentStep = 0;
        $this->totalSteps = 0;
        $this->dispatch('guide-closed');
    }

    protected function updateProgress(): void
    {
        if (auth()->check() && !empty($this->guide['id'])) {
            $progress = HelpUserProgress::where('user_id', auth()->id())
                ->where('guide_id', $this->guide['id'])
                ->first();

            if ($progress) {
                $progress->updateStep($this->currentStep + 1);
            }
        }
    }

    public function getCurrentStepData(): array
    {
        if (empty($this->guide['steps']) || !isset($this->guide['steps'][$this->currentStep])) {
            return [];
        }

        return $this->guide['steps'][$this->currentStep];
    }

    public function render()
    {
        return view('knowledgebase::livewire.screen-guide', [
            'stepData' => $this->getCurrentStepData(),
        ]);
    }
}
