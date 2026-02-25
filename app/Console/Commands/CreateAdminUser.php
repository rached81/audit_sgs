<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Admin user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Création du premier administrateur (ou réinitialisation).');

        $matricule = $this->ask('Matricule', 'admin');
        
        $user = User::where('matricule', $matricule)->first();

        if ($user) {
            if ($this->confirm("L'utilisateur avec le matricule '$matricule' existe déjà. Voulez-vous mettre à jour son mot de passe et le passer Admin ?", true)) {
                $password = $this->secret('Nouveau mot de passe');
                
                $user->update([
                    'password' => Hash::make($password),
                    'profile' => 'admin'
                ]);
                $this->info("Utilisateur mis à jour avec succès !");
            }
        } else {
            $nom = $this->ask('Nom', 'Administrateur');
            $prenom = $this->ask('Prénom', 'System');
            $email = $this->ask('Email', 'admin@sgs.tn');
            $username = $this->ask('Username', 'admin');
            $password = $this->secret('Mot de passe');

            User::create([
                'nom' => $nom,
                'prenom' => $prenom,
                'name' => "$nom $prenom",
                'unite' => 'DSI',
                'matricule' => $matricule,
                'email' => $email,
                'username' => $username,
                'password' => Hash::make($password),
                'profile' => 'admin'
            ]);
            
            $this->info("Administrateur créé avec succès !");
        }
    }
}
