<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Cron extends CI_Controller {

    public function create_brackets() {
        cron_require();
        $_THIRD_PLACE = THIRD_PLACE;
        $this->load->model('tournament_model');
        $tournaments = $this->tournament_model->get_tournaments_to_generate_brackets();

        foreach ($tournaments as $tournament) {
            // traigo los inscriptos
            if ($tournament->team_require) {
                $participants = $this->tournament_model->get_teams($tournament->id);
            } else {
                $participants = $this->tournament_model->get_users($tournament->id);
            }

            // los mezclo
            shuffle($participants);

            // divido a los inscriptos
            $matches = array_chunk($participants, 2);

            $count_participants = count($participants);

            $count_matches = ceil($count_participants / 2);

            $count_brackets = ceil(log($count_matches, 2)) + 1;

            $tournament->brackets = array();

            // GENERO TODOS LOS LUGARES DE LOS BRACKETS
            $temp_count_matches = $count_matches;
            $round_participants = $count_participants;

            for ($i_bracket = $count_brackets; $i_bracket > 0; $i_bracket--) {
                // CREO EL BRACKET
                $tournament->brackets[$i_bracket] = array();

                $temp_participants = $round_participants;
                for ($i_match = 0; $i_match < $temp_count_matches; $i_match++) {
                    // CREO EL MATCH
                    $tournament->brackets[$i_bracket][$i_match] = new stdClass();
                    $tournament->brackets[$i_bracket][$i_match]->player1 = false;
                    if ($temp_participants >= 2) {
                        $tournament->brackets[$i_bracket][$i_match]->player2 = false;
                    } else {
                        $tournament->brackets[$i_bracket][$i_match]->player2 = null;
                    }
                    $temp_participants = $temp_participants - 2;
                }
                $temp_count_matches = ceil($temp_count_matches / 2);
                $round_participants = ceil($round_participants / 2);
            }
            $tournament->brackets[$_THIRD_PLACE] = array();
            $tournament->brackets[$_THIRD_PLACE][0] = new stdClass();
            $tournament->brackets[$_THIRD_PLACE][0]->player1 = false;
            $tournament->brackets[$_THIRD_PLACE][0]->player2 = false;

            $tournament->settings->round = $count_brackets;

            $tournament->settings->quantities = new stdClass();
            $tournament->settings->quantities->participants = $count_participants;
            $tournament->settings->quantities->matches = $count_matches;
            $tournament->settings->quantities->brackets = $count_brackets;

            // TRAIGO LA FECHA LIMITE DE LA PRIMER RONDA
            $key = KEY_DATES_PREFIX . $count_brackets;
            $limit = $tournament->start_at + $tournament->settings->rounds->$key->expiration_time;
            $best_of = $tournament->settings->rounds->$key->best_of;

            foreach ($matches as $index => $match) {

                $temp_bracket = $tournament->brackets[$count_brackets][$index];

                $temp_bracket->player1 = $match[0];

                if (isset($match[1])) {
                    $temp_bracket->player2 = $match[1];
                    $temp_bracket->next_match = null;
                    $temp_bracket->match_id = $this->tournament_model->create_match(array(
                        'player1_id' => $temp_bracket->player1->id,
                        'player2_id' => $temp_bracket->player2->id,
                        'tournament_id' => $tournament->id,
                        'ends_at' => $limit,
                        'best_of' => $best_of
                    ));
                }

                $tournament->brackets[$count_brackets][$index] = $temp_bracket;
            }

            $this->tournament_model->update_tournaments_settings($tournament->id, $tournament->settings);
            $this->tournament_model->update_tournaments_brackets($tournament->id, $tournament->brackets);
        }
    }

    public function update_brackets() {
        cron_require();

        $this->load->model('tournament_model');
        $tournaments = $this->tournament_model->get_tournaments_to_update_brackets();
        foreach ($tournaments as $tournament) {
            $round = $tournament->settings->round;
            $next_round = ($round == 1) ? false : $round - 1; // Si round es 1 es la final
              
            switch ($round) {
                case 1:
                    //FINAL
                    $this->final_round($tournament);
                    break;
                case 2:
                    //SEMIFINAL
                    $this->semi_final_round($tournament);
                    break;
                default:
                    if ($this->normal_round($tournament)) {
                        $this->go_to_next_normal_round($tournament, $next_round);
                    }
                    break;
            }
            $this->tournament_model->update_tournaments_brackets($tournament->id, $tournament->brackets);
            $this->tournament_model->update_tournaments_settings($tournament->id, $tournament->settings);
        } //End each tournament
    }

    private function normal_round(&$tournament) {
        $round = $tournament->settings->round;
        $next_round = ($round == 1) ? false : $round - 1; // Si round es 1 es la final
        $matches_count = count($tournament->brackets->$round);
        $matches_done = 0;
        foreach ($tournament->brackets->$round as $index => $match) {
                        
            $place = floor($index / 2);
            // PLAYER_PLACE = 0 => PLAYER 1 - PLAYER_PLACE = 1 => PLAYER 2
            $player_place = $index % 2;

            $player = ($player_place == 0) ? 'player1' : 'player2';

            $next_matches = $tournament->brackets->$next_round;
            $next_match = $next_matches[$place];

            // SI YA SE LE APLICÓ UN TRATAMIENTO A ESTE MATCH
            if ($next_match->$player !== false) {
                $matches_done++;
                continue;
            }
            
            if (!isset($match->match_id)) {
                // ALGUN JUGADOR NO TIENE OPONENTE, PASA A LA SIGUIENTE RONDA
                if (is_null($match->player2)) {
                    $this->match_victory($match_db, 1);
                    $next_match->$player = $match->player1;
                    $matches_done++;
                } else if (is_null($match->player1)) {
                    $this->match_victory($match_db, 2);
                    $next_match->$player = $match->player2;
                    $matches_done++;
                }
            } else {
                
                $match_db = $this->tournament_model->get_match($match->match_id);
                
                // SI NO ENCUENTRA UN MATCH
                if (!$match_db) {
                    $this->tournament_model->update_tournament($tournament->id, array(
                        'problem' => TOURNAMENT_PROBLEM_MATCH
                    ));
                    continue;
                }

                // SI TIENE PROBLEMAS NO LO RECORREMOS
                if (!is_null($match_db->problem)) {
                    continue;
                }
                
                // SI YA GANO ALGUNO PASA EL PERTINENTE
                if (!is_null($match_db->winner)) {
                    switch ($match_db->winner) {
                        case 1:
                            $this->match_victory($match_db, 1);
                            $next_match->$player = $match->player1;
                            break;
                        case 2:
                            $this->match_victory($match_db, 2);
                            $next_match->$player = $match->player2;
                            break;
                    }
                    $matches_done++;
                }
                // SI EXPIRÓ EL MATCH
                else if ($match_db->ends_at <= get_date()->getTimestamp()) {
                    // SI AMBOS NO VOTARON
                    if (is_null($match_db->data->player1->winner) && is_null($match_db->data->player2->winner)) {
                        $this->tournament_model->update_tournament($tournament->id, array(
                            'match_problem' => MATCH_NO_WINNER
                        ));
                        $this->tournament_model->update_match($match_db->id, array(
                            'problem' => MATCH_NO_WINNER
                        ));
                        continue;
                    }
                    // ENTONCES, SI VOTO EL 2
                    else if (is_null($match_db->data->player1->winner)) {
                        $winner = $match_db->data->player2->winner;
                    }
                    // SINO, VOTO SOLO EL 1
                    else {
                        $winner = $match_db->data->player1->winner;
                    }
                    switch ($winner) {
                        case 1:
                            $this->match_victory($match_db, 1);
                            $next_match->$player = $match->player1;
                            break;
                        case 2:
                            $this->match_victory($match_db, 2);
                            $next_match->$player = $match->player2;
                            break;
                        default:
                            $this->match_victory($match_db, 0);
                            $next_match->$player = null;
                            break;
                    }
                    $matches_done++;
                }
            } //If match_id
        } //End each match
        return ($matches_count === $matches_done);
    }

    private function semi_final_round(&$tournament) {
        //$THIRD_PLACE = THIRD_PLACE;
        $SEMI_FINAL = '2';
        $round = $tournament->settings->round;
        //$next_round = ($round == 1) ? false : $round - 1; // Si round es 1 es la final
        
        if ($this->normal_round($tournament)) {
            
            $matches_semi_final = $tournament->brackets->$SEMI_FINAL;
            //$third_place_match = $tournament->brackets->$THIRD_PLACE;

            $tournament->settings->round = 1;
            
            $count_first_match = $this->count_players($matches_semi_final[0]);
            $count_second_match = $this->count_players($matches_semi_final[1]);

            $tournament->settings->first_place = false;
            $tournament->settings->second_place = false;
            $tournament->settings->third_place = false;

            // SI NO HAY JUGADORES EN ALGUNA DE LAS DOS PARTIDAS
            if ($count_first_match === 0 || $count_second_match === 0) {
                $this->tournament_model->update_tournament($tournament->id, array(
                    'problem' => TOURNAMENT_FINAL_PROBLEM
                ));
            } else if ($count_first_match === 1 && $count_second_match === 1) {
                $this->tournament_model->update_tournament($tournament->id, array(
                    'problem' => TOURNAMENT_THIRD_PLACE_PROBLEM
                ));
            } else {
                $final_ready = $this->handle_final($tournament);
                $third_place_ready = $this->handle_third_place($tournament);
            }
        }
    }

    private function count_players($match) {
        if (is_null($match->player1) && is_null($match->player2)) {
            return 0;
        } else if (!is_null($match->player1) && !is_null($match->player1)) {
            return 2;
        } else {
            return 1;
        }
    }

    private function final_round(&$tournament) {
        $FINAL = '1';
        $final_match = $tournament->brackets->$FINAL;

        // MANEJO LA FINAL
        if (!isset($final_match->match_id)) {
            $this->tournament_model->update_tournament($tournament->id, array(
                'problem' => TOURNAMENT_FINAL_PROBLEM
            ));
        } else {
            $match_db = $this->tournament_model->get_match($final_match->match_id);
            if (!is_null($match_db->winner)) { //SI HAY UN GANADOR
                switch ($match_db->winner) {
                    case 1:
                        $this->match_victory($match_db, 1);
                        break;
                    case 2:
                        $this->match_victory($match_db, 2);
                        break;
                }
                $winner = $match_db->winner;
            } else if ($match_db->ends_at <= get_date()->getTimestamp()) { //EXPIRA SIN GANADOR
                // SI AMBOS NO VOTARON
                if (is_null($match_db->data->player1->winner) && is_null($match_db->data->player2->winner)) {
                    $this->tournament_model->update_tournament($tournament->id, array(
                        'match_problem' => MATCH_NO_WINNER
                    ));
                    $this->tournament_model->update_match($match_db->id, array(
                        'problem' => MATCH_NO_WINNER
                    ));
                    $winner = 0;
                }
                // ENTONCES, SI VOTO EL 2
                else if (is_null($match_db->data->player1->winner)) {
                    $winner = $match_db->data->player2->winner;
                }
                // SINO, VOTO SOLO EL 1
                else {
                    $winner = $match_db->data->player1->winner;
                }
            }

            switch ($winner) {
                case 1:
                    $this->match_victory($match_db, 1);
                    $tournament->settings->first_place = $final_match->player1;
                    $tournament->settings->second_place = $final_match->player2;
                    // DAR PUNTOS 
                    break;
                case 2:
                    $this->match_victory($match_db, 2);
                    $tournament->settings->first_place = $final_match->player2;
                    $tournament->settings->second_place = $final_match->player1;
                    // DAR PUNTOS
                    break;
                case 0:
                    //$this->match_victory($match_db, 0);
                    //$tournament->settings->first_place = null;
                    //$tournament->settings->second_place = null;
                    $this->tournament_model->update_tournament($tournament->id, array(
                        'problem' => TOURNAMENT_FINAL_PROBLEM
                    ));
                    break;
            }
        } //If match id
        // MANEJO EL TERCER PUESTO
        $THIRD_PLACE = THIRD_PLACE;
        $third_place_match = $tournament->brackets->$THIRD_PLACE;
        if (!isset($third_place_match->match_id)) {
            // SI HAY ALGUN PLAYER ES EL 3ER SINO PROBLEM EN TOURNAMENT
            if (is_null($third_place_match->player1) && is_null($third_place_match->player2)) {
                $tournament->settings->third_place = null;
                $this->tournament_model->update_tournament($tournament->id, array(
                    'problem' => TOURNAMENT_THIRD_PLACE_PROBLEM
                ));
            } else if (is_null($third_place_match->player1)) {
                $tournament->settings->third_place = $third_place_match->player2;
            } else {
                $tournament->settings->third_place = $third_place_match->player1;
            }
        } else {
            $match_db = $this->tournament_model->get_match($third_place_match->match_id);
            if (!is_null($match_db->winner)) { //SI HAY UN GANADOR
                switch ($match_db->winner) {
                    case 1:
                        $this->match_victory($match_db, 1);
                        break;
                    case 2:
                        $this->match_victory($match_db, 2);
                        break;
                }
                $winner = $match_db->winner;
            } else if ($match_db->ends_at <= get_date()->getTimestamp()) { //EXPIRA SIN GANADOR
                // SI AMBOS NO VOTARON
                if (is_null($match_db->data->player1->winner) && is_null($match_db->data->player2->winner)) {
                    $this->tournament_model->update_tournament($tournament->id, array(
                        'match_problem' => MATCH_NO_WINNER
                    ));
                    $this->tournament_model->update_match($match_db->id, array(
                        'problem' => MATCH_NO_WINNER
                    ));
                    $winner = 0;
                }
                // ENTONCES, SI VOTO EL 2
                else if (is_null($match_db->data->player1->winner)) {
                    $winner = $match_db->data->player2->winner;
                }
                // SINO, VOTO SOLO EL 1
                else {
                    $winner = $match_db->data->player1->winner;
                }
            }
            switch ($winner) {
                case 1:
                    $this->match_victory($match_db, 1);
                    $tournament->settings->third_place = $third_place_match->player1;
                    // DAR PUNTOS
                    break;
                case 2:
                    $this->match_victory($match_db, 2);
                    $tournament->settings->third_place = $third_place_match->player2;
                    // DAR PUNTOS
                    break;
                case 0:
                    //$this->match_victory($match_db, 0);
                    //$tournament->settings->third_place = null;
                    $this->tournament_model->update_tournament($tournament->id, array(
                        'problem' => TOURNAMENT_THIRD_PLACE_PROBLEM
                    ));
                    break;
            }
        }

        if ($tournament->settings->first_place &&
            $tournament->settings->second_place &&
            $tournament->settings->third_place) {
            $this->tournament_model->update_tournament($tournament->id, array(
                'ends_at' => get_date()->getTimestamp(),
                'match_problem' => null,
                'problem' => null
            ));
        }
    }

    private function go_to_next_normal_round(&$tournament) {
        $round = $tournament->settings->round;
        $next_round = ($round == 1) ? false : $round - 1; // Si round es 1 es la final
        $tournament->settings->round = $next_round;
        $key = KEY_DATES_PREFIX . $next_round;
        $limit = get_date()->getTimestamp() + $tournament->settings->rounds->$key->expiration_time;
        $best_of = $tournament->settings->rounds->$key->best_of;
        
        foreach ($tournament->brackets->$next_round as $match) {
            if (!is_null($match->player1) && !is_null($match->player2)) {
                $match->match_id = $this->tournament_model->create_match(array(
                    'player1_id' => $match->player1->id,
                    'player2_id' => $match->player2->id,
                    'tournament_id' => $tournament->id,
                    'ends_at' => $limit,
                    'best_of' => $best_of
                ));
            }
        }
        $this->tournament_model->update_tournament($tournament->id, array(
            'match_problem' => null
        ));
    }

    private function handle_final(&$tournament) {
        $SEMI_FINAL = '2';
        $FINAL = '1';

        $matches_semi_final = $tournament->brackets->$SEMI_FINAL;

        $final_match = reset($tournament->brackets->$FINAL);
        
        $final_player1 = $this->get_winner($matches_semi_final[0]);
        $final_player2 = $this->get_winner($matches_semi_final[1]);

        if ($final_player1 === false || $final_player2 === false) {
            $this->tournament_model->update_tournament($tournament->id, array(
                'problem' => TOURNAMENT_PROBLEM_MATCH
            ));
        } else if (is_null($final_player1) || is_null($final_player2)) {
            $this->tournament_model->update_tournament($tournament->id, array(
                'problem' => TOURNAMENT_FINAL_PROBLEM
            ));
        } else {
            // CREO LA FINAL
            $final_key = KEY_DATES_PREFIX . '1';

            $final_match->player1 = $final_player1;
            $final_match->player2 = $final_player2;
            $final_match->match_id = $this->tournament_model->create_match(array(
                'player1_id' => $final_match->player1->id,
                'player2_id' => $final_match->player2->id,
                'tournament_id' => $tournament->id,
                'ends_at' => get_date()->getTimestamp() + $tournament->settings->rounds->$final_key->expiration_time,
                'best_of' => $tournament->settings->rounds->$final_key->best_of
            ));
            return true;
        }
        return false;
    }

    private function handle_third_place(&$tournament) {
        $THIRD_PLACE = THIRD_PLACE;
        $SEMI_FINAL = '2';
        $third_place_match = reset($tournament->brackets->$THIRD_PLACE);

        $matches_semi_final = $tournament->brackets->$SEMI_FINAL;
        
        $third_place_player1 = $this->get_loser($matches_semi_final[0]);
        $third_place_player2 = $this->get_loser($matches_semi_final[1]);

        if ($third_place_player1 === false || $third_place_player2 === false) {
            $this->tournament_model->update_tournament($tournament->id, array(
                'problem' => TOURNAMENT_PROBLEM_MATCH
            ));
        } else if (is_null($third_place_player1) && is_null($third_place_player2)) {
            $this->tournament_model->update_tournament($tournament->id, array(
                'problem' => TOURNAMENT_THIRD_PLACE_PROBLEM
            ));
        } else {
            // CREO EL THIRD PLACE MATCH
            $third_key = KEY_DATES_PREFIX . THIRD_PLACE;

            $third_place_match->player1 = $third_place_player1;
            $third_place_match->player2 = $third_place_player2;

            if (!is_null($third_place_player1) && !is_null($third_place_player2)) {
                $third_place_match->match_id = $this->tournament_model->create_match(array(
                    'player1_id' => $third_place_player1->id,
                    'player2_id' => $third_place_player2->id,
                    'tournament_id' => $tournament->id,
                    'ends_at' => get_date()->getTimestamp() + $tournament->settings->rounds->$third_key->expiration_time,
                    'best_of' => $tournament->settings->rounds->$third_key->best_of
                ));
            } else {
                $third_place_match->match_id = null;
            }
            return true;
        }
        return false;
    }

    private function get_loser($match) {
        if (isset($match->match_id)) {

            $match_db = $this->tournament_model->get_match($match->match_id);

            if (!$match_db) {
                return false;
            }

            switch ($match_db->winner) {
                case 1:
                    return $match->player2; //PLAYER2 PERDIO LA SEMI
                case 2:
                    return $match->player1; //PLAYER1 PERDIO LA SEMI
                default:
                    return null;
            }
        } else {
            return null;
        }
    }

    private function get_winner($match) {
        if (isset($match->match_id)) {
            $match_db = $this->tournament_model->get_match($match->match_id);

            if (!$match_db) {
                return false;
            }

            switch ($match_db->winner) {
                case 1:
                    return $match->player1; //PLAYER2 PERDIO LA SEMI
                case 2:
                    return $match->player2; //PLAYER1 PERDIO LA SEMI
                default:
                    return null;
            }
        } else if ($match->player1) {
            return $match->player1;
        } else {
            return $match->player2;
        }
    }

    private function match_victory(&$match_db, $player_number) {
        $this->player_wins($match_db, $player_number);
        $this->match_won($match_db, $player_number);
    }

    private function player_wins(&$match_db, $player_number) {
        switch ($player_number) {
            case 1:
                $match_db->data->sub_matches = $match_db->data->player1->sub_matches;
                $match_db->data->p1_wins = $match_db->data->player1->p1_wins;
                $match_db->data->p2_wins = $match_db->data->player1->p2_wins;
                break;
            case 2:
                $match_db->data->sub_matches = $match_db->data->player2->sub_matches;
                $match_db->data->p1_wins = $match_db->data->player2->p1_wins;
                $match_db->data->p2_wins = $match_db->data->player2->p2_wins;
                break;
            default:
                $match_db->data->sub_matches = array();
                $match_db->data->p1_wins = -1;
                $match_db->data->p2_wins = -2;
                break;
        }
    }

    private function match_won($match_db, $player_number) {
        $this->tournament_model->update_match($match_db->id, array(
            'winner' => $player_number,
            'closed_at' => get_date()->getTimestamp(),
            'data' => json_encode($match_db->data)
        ));
    }

    public function clear_unconfirmed_inscriptions() {
        cron_require();
        $this->load->model('tournament_model');
        $this->tournament_model->delete_unconfirmed();
    }

    public function clear_unconfirmed_users() {
        cron_require();
    }

    public function clear_unused_images() {
        cron_require();
    }

}
