<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    /**
     * @param  ?string  $title  หัวข้อหน้า (h1) และชื่อแท็บ
     * @param  ?string  $description  คำอธิบายสั้นใต้หัวข้อ
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
    ) {}

    public function render(): View
    {
        return view('layouts.guest');
    }
}
