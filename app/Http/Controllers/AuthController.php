<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    protected $baseUrl;
    
    public function __construct()
    {
        $this->baseUrl = env('FRAPPE_URL', 'http://erpnext.localhost:8000/');
    }
    
    /**
     * Vérifie si l'utilisateur est authentifié
     */
    protected function checkAuth()
    {
        if (!Session::has('frappe_sid')) {
            return false;
        }
        return true;
    }
    
    /**
     * Afficher le formulaire de connexion
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }
    
    /**
     * Gérer la tentative de connexion
     */
    public function login(Request $request)
    {
        $request->validate([
            'usr' => 'required',
            'pwd' => 'required',
        ]);
        
        try {
            $response = Http::post($this->baseUrl . '/api/method/login', [
                'usr' => $request->usr,
                'pwd' => $request->pwd,
            ]);
            
            if ($response->successful()) {
                $data = $response->json();

                $cookies = $response->cookies();
                $sidCookie = collect($cookies)->first(function ($cookie) {
                    return $cookie->getName() === 'sid';
                });
                
                if ($sidCookie) {
                    Session::put('frappe_sid', $sidCookie->getValue());
                    Session::put('user_full_name', $data['full_name'] ?? $request->usr);
                    
                    return redirect()->route('payroll.stats.index')->with('success', 'Connexion réussie');
                }
                
                return back()->withErrors(['message' => 'Erreur dans la récupération du cookie d\'authentification']);
            }
            
            return back()->withErrors(['message' => 'Identifiants incorrects']);
            
        } catch (\Exception $e) {
            return back()->withErrors(['message' => 'Erreur de connexion au serveur: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Déconnecter l'utilisateur
     */
    public function logout()
    {
        // Vérification de l'authentification
        if (!$this->checkAuth()) {
            return redirect()->route('login');
        }
        
        try {
            // Si SID est stocké en session
            if (Session::has('frappe_sid')) {
                $sid = Session::get('frappe_sid');
                
                // Appel à l'API de déconnexion
                $response = Http::withHeaders([
                    'Cookie' => 'sid=' . $sid
                ])->get($this->baseUrl . '/api/method/logout');
                
                // Supprimer les données de session
                Session::forget('frappe_sid');
                Session::forget('user_full_name');
            }
            
            return redirect()->route('login')->with('success', 'Vous avez été déconnecté');
            
        } catch (\Exception $e) {
            return back()->withErrors(['message' => 'Erreur lors de la déconnexion: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Récupérer l'utilisateur connecté actuel
     */
    public function getLoggedUser()
    {
        if (!$this->checkAuth()) {
            return response()->json(['error' => 'Non connecté'], 401);
        }
        
        try {
            $sid = Session::get('frappe_sid');
            
            // Appel à l'API pour obtenir l'utilisateur connecté
            $response = Http::withHeaders([
                'Cookie' => 'sid=' . $sid
            ])->get($this->baseUrl . '/api/method/frappe.auth.get_logged_user');
            
            if ($response->successful()) {
                return response()->json($response->json());
            }
            
            // Si problème d'authentification, supprimer la session
            Session::forget('frappe_sid');
            Session::forget('user_full_name');
            
            return response()->json(['error' => 'Session expirée'], 401);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Dashboard après connexion
     */
    public function dashboard()
    {
        if (!$this->checkAuth()) {
            return redirect()->route('login');
        }
        
        $userName = Session::get('user_full_name', 'Utilisateur');
        
        return view('calendar.index', compact('userName'));
    }
}