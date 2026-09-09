<?php

namespace App\Http\Controllers\Admin;

class DemoRequestController extends AdminController
{
    public function index()
    {
        return $this->page('demorequests', [
            'title' => 'Demandes de démo',
            'requests' => [
                ['company' => 'Société A', 'name' => 'M. Diallo', 'email' => 'diallo@example.com', 'status' => 'Nouvelle'],
                ['company' => 'Campus B', 'name' => 'Mme Yao', 'email' => 'yao@example.com', 'status' => 'En cours'],
                ['company' => 'Formation C', 'name' => 'M. Koffi', 'email' => 'koffi@example.com', 'status' => 'Confirmée'],
            ],
        ]);
    }
}
