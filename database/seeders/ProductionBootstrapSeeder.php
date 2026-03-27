<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $matricule = (string) env('BOOTSTRAP_ADMIN_MATRICULE', '19684');
        $nom = (string) env('BOOTSTRAP_ADMIN_NOM', 'BENKHALIFA');
        $prenom = (string) env('BOOTSTRAP_ADMIN_PRENOM', 'Rached');
        $email = (string) env('BOOTSTRAP_ADMIN_EMAIL', 'rached.benkhalifa@sgs.tn');
        $username = (string) env('BOOTSTRAP_ADMIN_USERNAME', 'rached19684');
        $unite = (string) env('BOOTSTRAP_ADMIN_UNITE', 'DSI');
        $tempPassword = (string) env('BOOTSTRAP_ADMIN_TEMP_PASSWORD', 'ChangeMe#19684');

        $payload = [
            'name' => trim($prenom . ' ' . $nom),
            'nom' => $nom,
            'prenom' => $prenom,
            'unite' => $unite,
            'matricule' => $matricule,
            'email' => $email,
            'username' => $username,
            'profile' => 'admin',
            'must_change_password' => true,
        ];

        $user = User::withTrashed()->where('matricule', $matricule)->first();
        if ($user) {
            $user->fill($payload);
            // Always refresh temp password during bootstrap to keep first-login flow deterministic.
            $user->password = Hash::make($tempPassword);
            if (method_exists($user, 'trashed') && $user->trashed()) {
                $user->restore();
            }
            $user->save();
            return;
        }

        User::create(array_merge($payload, [
            'password' => Hash::make($tempPassword),
        ]));
    }
}

