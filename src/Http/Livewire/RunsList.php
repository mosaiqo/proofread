<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('proofread::layout')]
class RunsList extends Component
{
    public function render(): View
    {
        return view('proofread::runs.list');
    }
}
