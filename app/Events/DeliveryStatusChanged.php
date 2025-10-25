<?php

namespace App\Events;

use App\Models\Delivery;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $delivery;
    public $oldStatus;
    public $newStatus;

    public function __construct(Delivery $delivery, $oldStatus, $newStatus)
    {
        $this->delivery = $delivery;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }
}
