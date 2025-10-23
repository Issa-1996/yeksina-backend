<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Lister tous les drivers en attente d'approbation
     */
    public function getPendingDrivers()
    {
        $drivers = Driver::where('is_approved', false)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $drivers
        ]);
    }

    /**
     * Approuver un driver
     */
    public function approveDriver($driverId)
    {
        $driver = Driver::findOrFail($driverId);

        if ($driver->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Ce driver est déjà approuvé.'
            ], 400);
        }

        $driver->update(['is_approved' => true]);

        // TODO: Envoyer une notification au driver (email/SMS)

        return response()->json([
            'success' => true,
            'message' => 'Driver approuvé avec succès',
            'data' => $driver->load('user')
        ]);
    }

    /**
     * Rejeter un driver
     */
    public function rejectDriver($driverId)
    {
        $driver = Driver::findOrFail($driverId);

        // Vous pouvez soit supprimer, soit marquer comme rejeté
        $driver->user()->delete(); // Supprime aussi l'utilisateur
        $driver->delete();

        return response()->json([
            'success' => true,
            'message' => 'Driver rejeté et supprimé avec succès'
        ]);
    }

    /**
     * Lister tous les drivers approuvés
     */
    public function getApprovedDrivers()
    {
        $drivers = Driver::where('is_approved', true)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $drivers
        ]);
    }
}