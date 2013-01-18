<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
	'pi_name' => 'DS Custom Channel Export',
	'pi_version' =>'0.1',
	'pi_author' =>'Dion Snoeijen',
	'pi_author_url' => 'http://www.diovisuals.com/',
	'pi_description' => 'Export channel data.',
	'pi_usage' => Ds_custom_channel_export::usage()
);

class Ds_custom_channel_export {	
	public  $return_data = '';

	private $channel;
	private $export_type;
	private $delimiter;
	private $filename;
	private $file_extension;
	private $add_title = false;
	
	private $DEFAULT = 'default';
	private $CLIEOP_3 = 'clieop3';

	private $clieop_test = 'P';
	private $CORRECT_NUMMER = 'correct_nummer';
	private $INCORRECT_NUMMER = 'incorrect_nummer';
	private $BANK = 'bank';
	private $GIRO = 'giro';
	private $YEAR = '';
	
	/** 
	 * Constructor
	 *
	 * @access public
	 * @return data
	 */
	public function __construct() 
	{
		$this->EE =& get_instance();
		
		$data = '';

		// -------------------------
		//	Channel to export from
		// -------------------------
		$this->channel = $this->EE->TMPL->fetch_param('channel', false);

		// -------------------------
		//	Export type: default or clieop3
		// -------------------------
		$this->export_type = $this->EE->TMPL->fetch_param('export_type', false);
		if($this->export_type != $this->CLIEOP_3) {
			$this->export_type = $this->DEFAULT;
		}

		// -------------------------
		//	Custom delimiter
		// -------------------------
		$this->delimiter = $this->EE->TMPL->fetch_param('delimiter', false);
		if(!$this->delimiter) {
			$this->delimiter = ';';
		}

		// -------------------------
		//	File extension
		// -------------------------
		$this->file_extension = $this->EE->TMPL->fetch_param('file_extension', false);
		if(!$this->file_extension) {
			$this->file_extension = '.csv';
		}

		if($this->channel && $this->export_type == $this->DEFAULT) {
			
			$where = array();

			if(isset($_POST['export'])) {
				// --------------------------
				//	Filename
				// --------------------------
				if($_POST['filename'] != '') {
					$this->filename = $_POST['filename'] . $this->file_extension;
				} else {
					$this->filename = false;
				}
				$data = $this->export_data(
					$this->get_field_ids($_POST['export']['fields']), 
					$this->get_where_fields($_POST['export']['where'])
				);
				// --------------------------
				//	return
				// --------------------------
				if($this->filename) {
					$this->EE->load->helper('download');
					force_download($this->filename, $data);
					exit;
				} else {
					$this->return_data = $data;
				}
			} else {
				$this->quick_debug('GEEF EXPORT VELDEN OP');
			}	
		} else {
			if($this->export_type == 'clieop3') {
				// --------------------------
				//	Year
				// --------------------------
				if(isset($_POST['year'])) {
					$this->YEAR = $_POST['year'];
				}

				// --------------------------
				//	Filename
				// --------------------------
				if($_POST['clieop_filename'] != '') {
					$this->filename = $this->prepare_clieop_filename($_POST['clieop_filename']) . $this->file_extension;
				} else {
					$this->filename = false;
				}
				if(isset($_POST['clieop_testversion'])) {
					$this->clieop_test = 'T';
				}

				// --------------------------
				//	Create clieop3 data
				// --------------------------
				if($this->YEAR != '') {
					$data = $this->create_clieop03();
					if($data) {
						if($this->filename) {
							$this->EE->load->helper('download');
							force_download($this->filename, $data);
						} else {
							$this->return_data = $data;
						}
					}
				} else {
					$this->quick_debug('GEEF JAAR OP');
				}
			} else {
				$this->quick_debug('GEEF CHANNEL OP');
			}
		}
	}

	private function prepare_clieop_filename($fn) {
		$fn = preg_replace('/\s+/', '', $fn);
		if(strlen($fn) < 8) {
			for($i = strlen($fn) ; $i < 8 ; $i++) {
				$fn = '0' . $fn;
			}
		} else {
			$fn = substr($fn, 0, 8);
		}

		return strtoupper($fn);
	}

	private function get_where_fields($f) {
		$where = array();
		if(isset($f)) {
			foreach($f as $key => $data) {
				$qr = $this->EE->db
					->query("SELECT field_id
							 FROM exp_channel_fields
							 WHERE field_name = '$key'")
					->result_array();

				if(isset($qr[0])) {
					$where['field_id_' . $qr[0]['field_id']] = $data;
				}
			}
		}
		return $where;
	}

	private function get_field_ids($f) {
		$fields = array();
		// --------------------------
		//	Fields
		// --------------------------
		foreach($f as $data) {
			$this->EE->db
				->select('field_id')
				->select('field_label')
				->where('field_name', $data);
			
			$q = $this->EE->db->get('channel_fields');
			$qr = $q->result();

			if(isset($qr[0])) {
				$fields[] = array(
					'field' => $data, 
					'id' => 'field_id_' . $qr[0]->field_id,
					'label' => $qr[0]->field_label
				);
			} else {
				if($data == 'title') {
					$this->add_title = true;
				}
			}
		}

		return $fields;
	}

	private function export_data($fields, $where) {
		if($this->get_channel_id()) {
			$data = '';

			$select_string = $this->create_select_string($fields);
			$where_string = $this->create_where_string($where);

			$qr = $this->EE->db
				->query("$select_string FROM exp_channel_data $where_string")
				->result_array();

			if(count($qr) > 0) {
				
				$data .= $this->filename ? '' : '<table class="table table-bordered">';
				// -------------------------
				//	Headings
				// -------------------------
				
				if(!$this->filename) {
					$data .= '<thead><tr>';
					$data .= '<th>Entry id</th>';
					if($this->add_title) {
						$data .= '<th>Voornaam</th>';
					}
					foreach ($fields as $field) {
						$data .= '<th>' . $field['label'] . '</th>';
					}
					$data .= '</tr></thead>';
				} else {
					$data .= 'Entry id' . $this->delimiter .
							 'Voornaam' . $this->delimiter;

					foreach ($fields as $field) {
						$data .= $field['label'] . $this->delimiter;
					}

					$data .= "\n";
				}
				
				// -------------------------
				//	All data
				// -------------------------
				$data .= $this->filename ? '' : '<tbody>';
				foreach($qr as $row) {
					$create_row = $this->filename ? '' : '<tr>';
					foreach ($row as $key => $value) {
						// -------------------------
						// Maak rijen
						// -------------------------
						if($this->filename) {
							$create_row .= str_replace('\n', '', $this->EE->db->escape_str(strip_tags($value))) . $this->delimiter;
						} else {
							$create_row .= '<td>' . str_replace('\n', '', $this->EE->db->escape_str(strip_tags($value))) . '</td>';
						}
						// -------------------------
						// Title toevoegen?
						// -------------------------
						if($key == 'entry_id' && $this->add_title) {
							$title_query = $this->EE->db->query("SELECT title 
																 FROM exp_channel_titles 
																 WHERE entry_id = '$value'");	
							if($title_query->num_rows() > 0) {
								foreach ($title_query->result_array() as $row) {
									if($this->filename) {
										$create_row .= $row['title'] . $this->delimiter;
									} else {
										$create_row .= '<td>' . $row['title'] . '</td>';
									}
								}
							}
						}
					}
					$data .= $this->filename ? substr_replace($create_row, '', -1) : $create_row;
					$data .= $this->filename ? "\n" : "</tr>";
				}
				if(!$this->filename) {
					$data .= $this->filename ? '' : '</tbody></table>';
				}
			} else {
				$this->quick_debug('GEEN RESULTATEN');
			}

			return $data;

		} else {
			$this->quick_debug('VERKEERD CHANNEL');
		}
	}

	/*( (machtiging = 'Ja') AND (betaald = 'Nee') AND (rekeningnummer != '') AND (status = 'betalend') )*/
	private function create_clieop03() {
		if($this->get_channel_id()) {
			$rekeningnummer_bzb = '0132263629';
			// -------------------------------------------------------------------- //
			//	BESTANDSVOORLOOPRECORD -------------------------------------------- //
			//	------------------------------------------------------------------- //
			//	naam 					| num of alf | karakters | voorbeeld *vast  //
			//	------------------------------------------------------------------- //
			//  Recordcode				|	9 		 |	4 		 |	0001* 			//
			//	Variantcode 			|	X 		 |	1 		 |	A* 				//
			//	Aanmaakdatum bestand 	|	9		 |	6 		 |	010113  		//
			//	Bestandsnaam 			|	X 		 |	8 		 |	CLIEOP03 		//
			//	Inzender identificatie	|	X 		 |	5		 |	FCBZB 			//
			//	Bestandsidentificatie	|	X 		 |  4		 | 	0801 			//
			//	Duplicaatcode			|	9		 |	1 		 |  1 				//
			//	Filler					|	X 		 |  21  	 | 	SPACE x 21 		//
			//	------------------------------------------------------------------- //
			if(!$this->filename) {
				$bestandsnaam = 'CLIEOP03';
			} else {
				$bestandsnaam = substr(strtoupper($this->filename), 0, 8);
			}

			$voorloop_record = $this->make_fifty_chars('0001A' . date('dmy') . $bestandsnaam . 'FCBZB08011');
			// -------------------------------------------------------------------- //
			//	BATCH VOORLOOPRECORD ---------------------------------------------- //
			//	------------------------------------------------------------------- //
			//	naam 					| num of alf | karakters | voorbeeld *vast  //
			//	------------------------------------------------------------------- //
			//	Recordcode				|	9		 |	4		 |	0010*	   		//
			//	Variantcode				|	X		 | 	1		 |	B of C 			//
			//	Transactiegroep 		| 	X		 | 	2		 |	10 				//
			//	Rekeningnummer opdrachtgever|	9	 | 	10		 | 	0132263629 		//
			//	Batchvolgnummer			|	9		 | 	4		 |	0001 			//
			//	Aanleveringsmuntsoort	|	X		 | 	3		 |	EUR 			//
			//	Batchidentificatie		| 	X		 | 	16		 |	SPACE x 16 		//
			//	Filler					|	X		 | 	10		 |	SPACE x 10 		//
			// -------------------------------------------------------------------- //
			$batch_voorloop_record = $this->make_fifty_chars('0010B10' . $rekeningnummer_bzb . '0001EUR');
			//	------------------------------------------------------------------- //
			//	VASTE OMSCHRIJVING RECORD ----------------------------------------- //
			//	------------------------------------------------------------------- //
			//	naam 					| num of alf | karakters | voorbeeld *vast  //
			//	------------------------------------------------------------------- //
			//	Recordcode				| 	9 		 |	4 		 |	0020*			//
			//	Variantcode				|	x 		 |	1 		 | 	A*				//
			//	Vaste omschrijving 		| 	X 		 |	max 32	 | 	OMSCHRIJVING 	//
			//	Filler 					| 	X 		 |  rest	 | 	SPACE x REST 	//
			//	------------------------------------------------------------------- //
			$vaste_omschrijving_record = $this->make_fifty_chars('0020ALIDMAATSCHAP FC BZB ' . $this->YEAR);
			//	------------------------------------------------------------------- //
			//	OPDRACHTGEVER RECORD ---------------------------------------------- //
			//	------------------------------------------------------------------- //
			//	naam 					| num of alf | karakters | voorbeeld *vast  //
			//	------------------------------------------------------------------- //
			//	Recordcode				|	9 		 |	4 		 |	0030*		  	//
			//	Variantcode				| 	X 		 |  1 		 |	B*				//
			//	NAWcode 				| 	9 		 |  1 		 |					//
			//	Gewenste verwerkingsdatum| 	9 		 |  6 		 |					//
			//	Naam opdrachtgever 		| 	X 	 	 | 	35 		 |					//
			//	Testcode 				|	X 		 |	1 		 |					//
			//	Filler 					|	X 		 |	2 		 |					//
			//	------------------------------------------------------------------- //
			$opdrachtgever_record = $this->make_fifty_chars('0030B1050512STICHTING FC BZB                   ' . $this->clieop_test);

			// --------------------------------------------------------------------
			//			***** EINDE GENEREER BESTANDSVOORLOOPRECORDS *****
			// --------------------------------------------------------------------

			// --------------------------------------------------------------------
			//	GENEREER RECORDS
			// --------------------------------------------------------------------
			$betaald_veld = 'la_betaald_' . $this->YEAR;
			$fields = array('la_machtiging', 
							'la_rek_nr', 
							$betaald_veld, 
							'la_lid_status',
							'la_tussenvoegsel',
							'la_achternaam',
							'la_woonplaats',
							'la_lidnummer');
			$ids = $this->get_field_ids($fields);

			$select_string = $this->create_select_string($ids);

			$machtiging = $ids[0]['id'];
			$rekeningnummer = $ids[1]['id'];
			$betaald = $ids[2]['id'];
			$status = $ids[3]['id'];
			$tussenvoegsel = $ids[4]['id'];
			$achternaam = $ids[5]['id'];
			$woonplaats = $ids[6]['id'];
			$lidnummer = $ids[7]['id'];

			$clieopdata = $this->EE->db->query("$select_string, entry_id	
								  				FROM exp_channel_data
								  				WHERE ($machtiging = 'y') AND ($betaald = 'n' OR $betaald = '') 
								  										  AND ($rekeningnummer != '') 
								  										  AND ($status = 'betalend')")
			->result_array();
			
			$count_correct_rek = 0;
			$totaal_reknrs = 0;
			foreach($clieopdata as &$data) {
				//  ------------------------------------------------------------------- //
				//	OMSCHRIJVING RECORD ----------------------------------------------- //
				//  ------------------------------------------------------------------- //
				//	naam 			| num of alf | karakters | voorbeeld *vast 			//
				//  ------------------------------------------------------------------- //
				//	Recordcode		|	9 		 | 	4 		 |	0160*	  				//
				//	Variantcode		|	X 		 | 	1 		 |	A*  					//
				//	Omschrijving 	|	X 		 |	MAX 32	 |	OMSCHRIJVING 			//
				//	Filler 			|	X 		 |	REST 	 |	SPACE x REST 			//
				// -------------------------------------------------------------------- //
				$data['omschrijving_record'] = $this->make_fifty_chars('0160ALIDNUMMER ' . $data[$lidnummer]);

				// -------------------------------------------------------------------- //
				//	NAAM BETEALER RECORD ---------------------------------------------- //
				//	------------------------------------------------------------------- //
				//	naam 			| num of alf | karakters | voorbeeld *vast 			//
				//	------------------------------------------------------------------- //
				//	Recordcode		|	9 		 |	4 		 |	0110* 					//
				//	Variantcode		|	X 		 | 	1 		 | 	B* 						//
				//	Naam betaler 	|	X 		 | 	MAX 35	 | 	PIETJE PUK 				//
				//	Filler 			|	X 		 | 	REST 	 | 	SPACE x REST 			//
				// -------------------------------------------------------------------- //
				$data['naam_betaler_record'] = $this->make_fifty_chars(strtoupper('0110B' . 
								$this->get_title($data['entry_id']) . ' ' .
								($data[$tussenvoegsel] == '' ? ('') : ($data[$tussenvoegsel] . ' ')) . 
								$data[$achternaam]));

				//	------------------------------------------------------------------- //
				//	WOONPLAATS BETALER RECORD (GENEGEERD?!) --------------------------- //
				//	------------------------------------------------------------------- //
				//	naam 			| num of alf | karakters | voorbeeld *vast 			//
				//	------------------------------------------------------------------- //
				//	Recordcode	 	|	9 		 |	4 		 | 	0113* 					//
				//	Variantcode	 	|	X 		 | 	1 		 | 	B* 						//
				//	Filler			|	X 		 | 	45 		 | 	SPACE x 45 				//
				//	------------------------------------------------------------------- //
				$data['woonplaats_betaler_record'] = $this->make_fifty_chars(strtoupper('0113B' . $data[$woonplaats]));

				//  ------------------------------------------------------------------- //
				//	TRANSACTIE RECORD ------------------------------------------------- //
				//	------------------------------------------------------------------- //
				//	naam 					   | num of alf | karakters | voorbeeld*vast//
				//	------------------------------------------------------------------- //
				//	Recordcode 				   |	9 		|	4 		| 	0100* 	 	//
				//	Variantcode				   |	X 		|	1 		|	A* 			//
				//	Transactiesoort 		   |	X 		|	4 		| 	100100000000//
				//	Bedrag					   |	9 		|	12 		| 	1500 		//
				//	Rekeningnummer betaler 	   |	9 		|	10 		|	0120129396 	//
				//	Rekeningnummer begunstigde |	9 		|	10 		|	0132263629  //
				//	Filler					   |	X 		| 	9 		|	SPACE x 9   //
				//  ------------------------------------------------------------------- //
				$bedrag = '1500';
				$data[$rekeningnummer] = $this->eleven_test($data[$rekeningnummer]);
				if($data[$rekeningnummer]['correct'] == $this->CORRECT_NUMMER ||
					$data[$rekeningnummer]['bank'] == $this->GIRO) {
					// --------------------------
					//	Voorbereiding sluitrecord
					// --------------------------
					$count_correct_rek++;
					$totaal_reknrs += intval($data[$rekeningnummer]['rekeningnummer']);
					$totaal_reknrs += intval($rekeningnummer_bzb);
					
					// --------------------------
					//	Trans record
					// --------------------------
					$data['transactie_record'] = '0100A100100000000';
					$data['transactie_record'] .= '1500';
					$data['transactie_record'] .= $data[$rekeningnummer]['rekeningnummer'];
					$data['transactie_record'] .= $rekeningnummer_bzb;
					$data['transactie_record'] = $this->make_fifty_chars($data['transactie_record']);
				} else {
					/*HANDLE INCORRECT BANKACCOUNT?!*/
				}
			}
			// -------------------------------------------------------------------- //
			//				  ***** EINDE GENEREER RECORDS ***** 					//
			// -------------------------------------------------------------------- //

			// -------------------------------------------------------------------- //
			//	GENEREER BATCHSLUITRECORD ----------------------------------------- //
			//	------------------------------------------------------------------- //
			//	naam 			| num of alf | karakters | voorbeeld *vast 			//
			//	------------------------------------------------------------------- //
			//	Recordcode		|	9		 |	4		 |	9990*					//
			//	Variantcode 	|	X		 |	1		 |	A*						//
			//	Totaalbedrag	|	9		 |	18		 |	000000000000012000		//
			//	Totaal rekeningnummers|9 	 |	10		 |	3371480090				//
			//	Aantal posten	|	9	  	 |	7		 |	0000008					//
			//	Filler			|	X		 |	10		 |							//
			// -------------------------------------------------------------------- //
			// 	9990 A 000000000000012000 3371480090 0000008						//
			// -------------------------------------------------------------------- //
			$totaal_bedrag = strval((intval($bedrag) * $count_correct_rek));
			for($i = strlen($totaal_bedrag) ; $i < 18 ; $i++) {
				$totaal_bedrag = '0' . $totaal_bedrag;
			}
			$totaal_reknrs = strval($totaal_reknrs);
			if(strlen($totaal_reknrs) > 10) {
				$totaal_reknrs = substr($totaal_reknrs, -10);
			} else {
				for($i = strlen($totaal_reknrs) ; $i < 10 ; $i++) {
					$totaal_reknrs = '0' . $totaal_reknrs;
				}
			}
			$count_correct_rek = strval($count_correct_rek);
			for($i = strlen($count_correct_rek) ; $i < 7 ; $i++) {
				$count_correct_rek = '0' . $count_correct_rek;
			}
			$batch_sluitrecord = $this->make_fifty_chars('9990A' . $totaal_bedrag . $totaal_reknrs . $count_correct_rek);

			// -------------------------------------------------------------------- //
			//	GENEREER BESTANDSSLUITRECORD -------------------------------------- //
			//	------------------------------------------------------------------- //
			//	naam 			| num of alf | karakters | voorbeeld *vast 			//
			//	------------------------------------------------------------------- //
			//	Recordcode		|	9		 |	4 		 |	9999*					//
			//	Variantcode		|	X 		 |	1 		 |	A* 						//
			//	Filler			|	X 		 |	45 		 |	SPACE x 45 				//
			// -------------------------------------------------------------------- //
			$sluit_record = $this->make_fifty_chars('9999A');
			// -------------------------------------------------------------------- //
			//			***** EINDE GENEREER BESTANDSSLUITRECORD ***** 				//
			// -------------------------------------------------------------------- //

			// -------------------------------------------------------------------- //
			//	GENEREER RESULTAAT ------------------------------------------------ //
			//	------------------------------------------------------------------- //
			$result = $voorloop_record . $this->line_break();
			$result .= $batch_voorloop_record . $this->line_break();
			$result .= $vaste_omschrijving_record . $this->line_break();
			$result .= $opdrachtgever_record . $this->line_break();
			foreach($clieopdata as $key=>$data) {
				if(isset($data['transactie_record'])) {
					$result .= $data['transactie_record'] . $this->line_break();
					$result .= $data['naam_betaler_record'] . $this->line_break();
					$result .= $data['woonplaats_betaler_record'] . $this->line_break();
					$result .= $data['omschrijving_record'] . $this->line_break();
				}
			}
			$result .= $batch_sluitrecord . $this->line_break();
			$result .= $sluit_record . $this->line_break();
			if(!$this->filename) {
				$result .= '<hr>';
			}
			return $result;
		} else {
			$this->quick_debug('VERKEERD CHANNEL');
		}
		return false;
	}

	private function line_break() {
		if($this->filename) {
			return "\n";
		} else {
			return "<br>\n";
		}
	}

	private function make_fifty_chars($transstring) {
		for($i = strlen($transstring) ; $i < 50 ; $i++) {
			$transstring .= ' ';
		}
		return $transstring;
	}

	private function get_title($entry) {
		$title = $this->EE->db->query("SELECT title 
				               		   FROM exp_channel_titles 
							  		   WHERE entry_id = '$entry'")
		->result_array();

		return $title[0]['title'];
	}

	/*
	 * 	De 11 proef.
	 *  Nakijken of het rekeningnummer klopt.
	 *	Altijd voorloop nul
	 */
	private function eleven_test($rekeningnummer) {
		// -------------------------
		//	Eerst nakijken of het een bankrekening nummer is.
		// -------------------------
		$count = strlen($rekeningnummer);
		$bank = $this->BANK;
		if($count < 9) {
			$bank = $this->GIRO;
		}
		// -------------------------
		//	Het rekeningnummer moet 10 cijfers bevatten.
		//	In het geval van gironummer meerdere nullen toevoegen.
		// -------------------------
		$nullen = '';
		for($i = strlen($rekeningnummer) ; $i < 10 ; $i++) {
			$nullen .= '0';
		}
		$rekeningnummer = $nullen . $rekeningnummer;

		// -------------------------
		// 	Zet teller naar 10
		// -------------------------
		$count = strlen($rekeningnummer);
		$countback = $count;
		$result = 0;
		// -------------------------
		//	De 11 proef. Vermenigvuldig het eerste getal met de countback, dan de tweede enz.
		//	Tel deze resultaten bij elkaar op.
		// -------------------------
		for($countup = 0 ; $countup < $count ; $countup++) {
			$work_with = substr($rekeningnummer, $countup, 1);
			$result += intval($work_with) * $countback;
			$countback -= 1;
		}
		// -------------------------
		//	Deel het resultaat door 11.
		// -------------------------
		$result = $result / 11;

		// -------------------------
		//	Is het resultaat een integer dan is het goed.
		// -------------------------
		$correct = $this->INCORRECT_NUMMER;
		if(is_int($result)) {
			$correct = $this->CORRECT_NUMMER;
		}
		
		// -------------------------
		//	Return relevante informatie
		// -------------------------
		return array('rekeningnummer' => $rekeningnummer, 
					 'correct' => $correct, 
					 'bank' => $bank);
	}

	private function quick_debug($data, $title = '') {
		echo '<pre>';
		echo $title;
		print_r($data);
		echo '</pre>';
	}

	private function create_select_string($fields) {
		$select_string = $this->add_title ? 'entry_id,' : '';
		
		foreach($fields as $field) {
			$select_string .= $this->EE->db->escape_str($field['id']) . ',';
		}
		$select_string = substr_replace($select_string, '', -1);

		return ("SELECT " . $select_string);
	}

	private function create_where_string($where) {
		$channel_id = $this->get_channel_id();
		$where_string = "WHERE (channel_id = '$channel_id') AND ";

		foreach($where as $key=>$data) {
			foreach($data as $subkey=>$value) {
				if(count($data) == 1) {
					$where_string .= ' (' . $key . ' = ';
					if(isset($value)) {
						$where_string .= "'" . $this->EE->db->escape_str($value) . "') AND ";
					}
				} else {
					if(isset($value)) {
						if($subkey == 0) {
							$where_string .= '(';
						}
						$where_string .= $key . " = '" . $this->EE->db->escape_str($value) . "'";
						if(($subkey + 1) == count($data)) {
							$where_string .= ') AND ';
						} else {
							$where_string .= ' OR  ';
						}
					}
				}	
			}
		}	

		$where_string = substr_replace($where_string, '', -5);

		return $where_string;
	}

	private function get_channel_id() {
		$qr = $this->EE->db->query("SELECT channel_id 
									FROM exp_channels 
									WHERE channel_name = '$this->channel'");

		if($qr->num_rows() > 0) {
			foreach($qr->result_array() as $row) {
    			return $row['channel_id'];
    		}
		}
		return false;
	}

	// usage instructions
	public function usage() 
	{
  		ob_start();
?>
-------------------
USAGE
-------------------
{exp:custom_channel_export channel="ledenadministratie" file_extension="{if segment_3 == ''}.csv{if:else}.txt{/if}" export_type="{segment_3}"}

	<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}	
}

/* End of file pi.ifelse.php */ 
/* Location: ./system/expressionengine/third_party/ifelse/pi.custom_channel_export.php */