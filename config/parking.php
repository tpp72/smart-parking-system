<?php

return [
    /*
     | Minutes after reserve_start that check-in is still allowed.
     | After this window the scheduler auto-expires the reservation.
     | Set RESERVATION_GRACE_PERIOD in .env to override.
     */
    'grace_period' => (int) env('RESERVATION_GRACE_PERIOD', 30),
];
