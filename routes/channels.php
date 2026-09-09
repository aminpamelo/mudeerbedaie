<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Cekbot real-time inbox — admins only (mirrors the /admin/cekbot route gate).
Broadcast::channel('cekbot-inbox', function ($user) {
    return $user->isAdmin();
});
