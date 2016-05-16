<?php

class Search_model extends CI_Model {
    public function search($key) {
    	$result = array();
    	$this->db->select('user.first_name, user.last_name, user.username , user.id as userID, images.file_name');
    	$this->db->from('user'); 
    	$this->db->join('images', 'user.image = images.id');
		$where = '(user.first_name LIKE "%' . $key . '%" OR user.last_name LIKE "%' . $key . '%" OR user.username LIKE "%' . $key . '%")';
		$this->db->where($where);
		$query = $this->db->get()->result_array();

		if (!empty($query)){
			$result['users'] = $query;
		}

		$this->db->select('tournament.id , tournament.name ');
        $this->db->from('tournament');
        $where = '(tournament.name LIKE "%' . $key . '%")';
		$this->db->where($where);

		$query = $this->db->get()->result_array();
				
		if (!empty($query)){
			$result['tournaments'] = $query;
		}

		return $result;
    }

}
