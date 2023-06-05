 public function calcola_giornata($rapporto_id, $datainit)
    {
        $data_time_evento = Carbon::now();
        //   dd($this->getturno(20,45,'2022-06-13'));

        $today = date('Y-m-d', strtotime($data_time_evento));
        if ($datainit < $today) {
            $giornate_elaborate = GiornateElaborate::query()
                ->where('rapporto_id', $rapporto_id)
                ->where('data', $datainit)->get();
            // dd(($giornate_elaborate));
            if (count($giornate_elaborate) < 1) {
                SlotFinaleTurnoTimbra::query()
                    //where('user_id', $userid)
                    ->where('rapporto_id', $rapporto_id)
                    ->where('data', $datainit)->delete();
                $step = \Config::get('constants.report_grafico_aderenza.step');
                $orastartglobal = \Config::get('constants.report_grafico_aderenza.ora_start');
                if ($orastartglobal <= 9) {
                    $orastartglobal = Carbon::parse("0" . $orastartglobal . ":00")->format('H:i');
                } else $orastartglobal = $orastartglobal . ":00";

                $oraendglobale = \Config::get('constants.report_grafico_aderenza.ora_end') . ":00";
//dd($rapporto_id);
                $turnigiorno = TurnoGiornaliero::getturno($rapporto_id, $datainit)->get();

                if (count($turnigiorno) > 0) {
                    foreach ($turnigiorno as $turnogiorno) {
                        //Verifica se è una giornata libera
                        $id_turno = $turnogiorno->id;
                        if ($turnogiorno->turno_id != 147 && $turnogiorno->turno_id != 148) {
                            // echo($turnogiorno->turno_id);
                            $inizio_turno = $turnogiorno->turno->inizio_turno;
                            $fine_turno = $turnogiorno->turno->fine_turno;

                            $step_turno = $this->timeSteps($step, $inizio_turno, $fine_turno, $id_turno);
                            //RICERCO SE CI SONO STRAORDINARI, SE CI SONO VANNO AGGIUNTI COME TURNO
                            $timbrature_giorno = Timbratura::gettimbraturegiorno($rapporto_id, $datainit)->orderBy('login')->get();

                            $query = array();
                            if (!$timbrature_giorno->isEmpty()) {

                                if($timbrature_giorno->count()==1){

                                $this->calcola_globale($timbrature_giorno, $fine_turno, $step, $inizio_turno, $step_turno, $rapporto_id, $datainit, $id_turno, 'Si');
                                }
                                else {
                                 //   echo($datainit);
                                //    dd($timbrature_giorno);
                                    $inizio=0;
                                    foreach ($timbrature_giorno as $timbratura){

                                        $start_time = new \Carbon\Carbon($timbratura->login);
                                        $end_time = new \Carbon\Carbon($timbratura->logout);
                                        $start_time = Carbon::parse($start_time)->format('H:i:00');
                                        $end_time = Carbon::parse($end_time)->format('H:i:00');
                                       // $inizio_prima_timbra="";
                                            if($inizio==0){
                                                $inizio_prima_timbra=$start_time;
                                             //   dd($start_time,$end_time);
                                              //  $step_timbra_spezzata=array_push($this->timeSteps($step, $start_time, $end_time, 0));
                                                $inizio++;

                                            }
                                          //  else {   $step_timbra_spezzata=$this->timeSteps($step, $start_time, $end_time, 0);}
                                            $fine_ultimo=$end_time;
                                    }
                                  //  dd("qui");
                                    $step_timbra_probabile_unita=$this->timeSteps($step, $inizio_prima_timbra, $fine_ultimo, 0);
                                    $this->calcola_globale($timbrature_giorno, $fine_turno, $step, $inizio_turno, $step_turno, $rapporto_id, $datainit, $id_turno, 'Si');

                                   /* foreach ($timbrature_giorno as $timbratura){
                                        $start_time = new \Carbon\Carbon($timbratura->login);
                                        $end_time = new \Carbon\Carbon($timbratura->logout);
                                        $start_time = Carbon::parse($start_time)->format('H:i:00');
                                        $end_time = Carbon::parse($end_time)->format('H:i:00');
                                        $step_timbra_spezzata=$this->timeSteps($step, $start_time, $end_time, 0);
                                       // dd($step_timbra_spezzata,$step_timbra_probabile_unita);

                                        $differenza = array_diff(array_map('json_encode',$step_timbra_probabile_unita),array_map('json_encode',$step_timbra_spezzata));
                                        $differenza=array_map('json_decode',$differenza);
                                       // dd($differenza);
                                        $step_timbra_probabile_unita = array_diff(array_map('json_encode', $step_timbra_probabile_unita), array_map('json_encode', $differenza));
                                        $step_timbra_probabile_unita = array_map('json_decode',$step_timbra_probabile_unita);
                                    }
dd($step_timbra_probabile_unita);*/



                                }

                            } else {
                                //NIENTE TIMBRATURE TUTTO ARANCIO AD ESCLUSIONI DELLE RICHIESTE
                                //QUINDI DEVO CREARE GLI STEP DELLA RICHIESTA SE ESISTONO E TOGLIERLE DALL'ARANCIO
                                //SE non ci sono timbrature significa che è una giornata intera!!!!
                                // dd("qui");
                                //  array_push($query, ['start_time' => $inizio_turno, 'end_time' => $fine_turno, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'assenze']);
                                //  SlotFinaleTurnoTimbra::insert($query);
                                $richiesteapprovate = DettaglioRichiesta::getrichiestegiornoapprovate($datainit, $rapporto_id)
                                    ->with("richiesta.tipologiaPermesso")
                                    ->whereHas('richiesta.tipologiaPermesso', function (Builder $query) {
                                        $query->where('is_giustificativo', '=', 'No');
                                        //   ->orwhere('is_extra','=','Si');
                                    })
                                    ->get();

//dd($richiesteapprovate);
//if($datainit=='2023-03-23') dd($richiesteapprovate);
                                if(count($richiesteapprovate)==0){
                                    //VERIFICO SE è un GG Festivo ed inserisco FESTIVO INTRASETTIMANALE
                                    $mese= Carbon::parse($datainit)->format('m');
                                    $giorno= Carbon::parse($datainit)->format('d');
                                    $cerca_festivita_fissa=FestivitaFisse::query()
                                        ->where('mese', $mese)
                                        ->where('giorno',$giorno)
                                        ->get();
                                    if(count($cerca_festivita_fissa)>0){
                                        //Inserisco Festività infrasettimanale
                                        $richiesta = new Richiesta();
                                        $richiesta->id_utente = 0;
                                        $richiesta->id_tipo_permesso = 19;
                                        $richiesta->data_inizio = Carbon::parse($datainit)->format('d/m/Y');
                                        $richiesta->data_fine = Carbon::parse($datainit)->format('d/m/Y');
                                        $richiesta->esito_richiesta = 6;
                                        $richiesta->rapporto_id = $rapporto_id;
                                        $richiesta->save();
                                        $id_richiesta = $richiesta->id;
                                        $richiesta_giornaliera = new DettaglioRichiesta();
                                        $richiesta_giornaliera->id_utente = 0;
                                        $richiesta_giornaliera->id_richiesta = $id_richiesta;
                                        $richiesta_giornaliera->data_richiesta = $datainit;;
                                        $richiesta_giornaliera->stato_richiesta = 3;
                                        $richiesta_giornaliera->rapporto_id = $rapporto_id;
                                        $richiesta_giornaliera->certificato_presentato = "Non Necessario";
                                        $richiesta_giornaliera->save();
                                        $richiesteapprovate = DettaglioRichiesta::getrichiestegiornoapprovate($datainit, $rapporto_id)
                                            ->with("richiesta.tipologiaPermesso")
                                            ->whereHas('richiesta.tipologiaPermesso', function (Builder $query) {
                                                $query->where('is_giustificativo', '=', 'No');
                                                //   ->orwhere('is_extra','=','Si');
                                            })
                                            ->get();
                                    }else {
                                        //controllo festività variabili
                                        $cerca_festivita_variabili=FestivitaVariabili::query()
                                            ->where('data_festa', $datainit)

                                            ->get();
                                        if(count($cerca_festivita_variabili)>0){
                                            $cerca_festivita_variabili=FestivitaVariabili::query()
                                                ->where('data_festa', $datainit)

                                                ->first();
                                                if($cerca_festivita_variabili->descrizione!='Pasqua'){
                                                    $richiesta = new Richiesta();
                                                    $richiesta->id_utente = 0;
                                                    $richiesta->id_tipo_permesso = 19;
                                                    $richiesta->data_inizio = Carbon::parse($datainit)->format('d/m/Y');
                                                    $richiesta->data_fine = Carbon::parse($datainit)->format('d/m/Y');
                                                    $richiesta->esito_richiesta = 6;
                                                    $richiesta->rapporto_id = $rapporto_id;
                                                    $richiesta->save();
                                                    $id_richiesta = $richiesta->id;
                                                    $richiesta_giornaliera = new DettaglioRichiesta();
                                                    $richiesta_giornaliera->id_utente = 0;
                                                    $richiesta_giornaliera->id_richiesta = $id_richiesta;
                                                    $richiesta_giornaliera->data_richiesta = $datainit;;
                                                    $richiesta_giornaliera->stato_richiesta = 3;
                                                    $richiesta_giornaliera->rapporto_id = $rapporto_id;
                                                    $richiesta_giornaliera->certificato_presentato = "Non Necessario";
                                                    $richiesta_giornaliera->save();
                                                    $richiesteapprovate = DettaglioRichiesta::getrichiestegiornoapprovate($datainit, $rapporto_id)
                                                        ->with("richiesta.tipologiaPermesso")
                                                        ->whereHas('richiesta.tipologiaPermesso', function (Builder $query) {
                                                            $query->where('is_giustificativo', '=', 'No');
                                                            //   ->orwhere('is_extra','=','Si');
                                                        })
                                                        ->get();
                                                }
                                        }else{
                                            //ASSENZA
                                              array_push($query, ['start_time' => $inizio_turno, 'end_time' => $fine_turno, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'assenze']);
                                              SlotFinaleTurnoTimbra::insert($query);
                                        }

                                    }
                                }
                              //  else {
                                foreach ($richiesteapprovate as $richiesta) {
                                    $start = Carbon::parse($richiesta->orada)->format('H:i');
                                    $end = Carbon::parse($richiesta->oraa)->format('H:i');
                                    $dettagli_richiesta_id = $richiesta->id;
                                    $tipo_richiesta = $richiesta->richiesta->tipologiaPermesso->name;
                                    if (!is_null($richiesta->orada)) {
                                        if ($richiesta->richiesta->tipologiaPermesso->no_need_timbra == "Si") {
                                            /*   $up = SlotFinaleTurnoTimbra::query()
                                                   //where('user_id', $userid)
                                                   ->where('rapporto_id', $rapporto_id)
                                                   ->where('data', $datainit)
                                                   ->where('start_time', '>=', $start)
                                                   ->where('end_time', '<=', $end)
                                                   ->where('tipologia', 'assenze')
                                                   ->update(['tipologia' => 'presenze', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta]);*/
                                            array_push($query, ['start_time' => $start, 'end_time' => $end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'presenze', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta]);
                                            SlotFinaleTurnoTimbra::insert($query);

                                        } else {

                                            /*  $up = SlotFinaleTurnoTimbra::query()
                                                  //where('user_id', $userid)
                                                  ->where('rapporto_id', $rapporto_id)
                                                  ->where('data', $datainit)
                                                  ->where('start_time', '>=', $start)
                                                  ->where('end_time', '<=', $end)
                                                  ->where('tipologia', 'assenze')
                                                  ->update(['tipologia' => 'giustificate', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta]);*/
                                           // if ($start == $inizio_turno && $end == $fine_turno) {
                                           //     array_push($query, ['start_time' => $start, 'end_time' => $end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'assenze', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta]);
                                           //     SlotFinaleTurnoTimbra::insert($query);
                                           // } else {
                                                array_push($query, ['start_time' => $start, 'end_time' => $end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'giustificate', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta]);
                                                //  SlotFinaleTurnoTimbra::insert($query);
                                              //  array_push($query, ['start_time' => $inizio_turno, 'end_time' => $fine_turno, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'assenze', 'dettagli_richiesta_id' => 0, 'tipo_richiesta' => '']);
                                                SlotFinaleTurnoTimbra::insert($query);
                                         //   }


                                        }
                                    } else {
                                        // dd("qio");
                                        array_push($query, ['start_time' => $inizio_turno, 'end_time' => $fine_turno, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'giustificate', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta]);
                                        SlotFinaleTurnoTimbra::insert($query);

                                    }

                                    }//FINE FOR
                               // }
                            }
                            //CERCO STRAORDINARI EXTRA TURNO

                        }
                        else {
                            $this->calcola_giornata_ST2($rapporto_id, $datainit,$id_turno);

                        }
                    }
                } else $this->calcola_giornata_ST2($rapporto_id, $datainit,0);
                $this->calcolaAderenza($rapporto_id, $datainit);
                //DEVO CHIUDERE LE GIORNATE OK

            }
        }
    }  





public function calcola_globale($timbrature_giorno, $fine_turno, $step, $inizio_turno, $step_turno, $rapporto_id, $datainit, $id_turno, $controlla_richieste)
    {
        $tolleranza = 11;
        //funzione che calcola la giornata in base alla timbratura e al turno che puo' essere turno
        //da turnistica oppure turno da straordinario nel caso in cui in una giornata dove non previsto il turno
        //questo diventa inizio e fine straordinario
        $step_completo_merge = array();
        //STEP FINALE MERGE RAPPRESENTA LA TIMBRATURA
        $step_finale_merge = array();
        $query = array();
        $tot_verdi = array();
        $init_rosso = array();
        $query_assenza = array();
        $i = 0;
        $non_cercare = false;
        $non_controllare = false;//mi verifica se c'è il logout nella giornata
        $dove="";
        //MI PRENDO LA TIPOLOGIA CONTRATTO PER STRAO O SUPPLEMENTARE
        $rapportodet = \App\Models\Hrm\rapporto_details::query()
            ->where('rapporto_id',$rapporto_id)
            ->where('data_inizio', '<=', $datainit)
            ->where(function ($querywhere) use ($datainit) {
                $querywhere->where('data_fine', '>=', $datainit)
                    ->orWhereNull('data_fine');
            })
            ->with('rapporto')
            ->with('tipo_contratto')->first();
        $tipo_contratto_ore=$rapportodet->tipo_contratto->ore_giornaliere;
        foreach ($timbrature_giorno as $timbratura) {
            $tutto_il_giorno = false;
            $i++;
            $in = $timbratura->login;
            $out = $timbratura->logout;
            $timbra_init = Carbon::parse($in)->format('H:i:00');
            $minutes = ((int)(Carbon::parse($timbra_init)->format('i')));
            //$minutestollerana=
            //Se timbro e 29 minuti considero alla mezzora solo nel caso in cui aggiungo la tolleranza
            //   if($minutes>$tolleranza){
            //   if($datainit=="2023-03-07") dd($minutes);
            // dd($minutes,Carbon::parse($timbra_init)->format('i'));
            if ($timbrature_giorno->count() == 1) {

            $needToRound = ($minutes % 30) > 0 ? 30 - ($minutes % 30) : 0;

            if ($minutes > 10)
                if ($minutes > 40)
                    $timestampRounded = ($minutes + $needToRound);
                else {
                    $timestampRounded = (30);
                }
            else   $timestampRounded = (0);

            if ($timestampRounded >= 60) {
                $timbra_init = ((int)Carbon::parse($timbra_init)->format('H')) + 1;
                if ($timbra_init < 10)
                    $timbra_init = "0" . $timbra_init . ":00";
                else $timbra_init = $timbra_init . ":00";


            } elseif ($timestampRounded >= 30) {
                $timbra_init = Carbon::parse($timbra_init)->format('H') . $timestampRounded;
            } elseif ($timestampRounded == 0) {
                $timbra_init = Carbon::parse($timbra_init)->format('H') . '00';

            }
        }

            $timbra_init = Carbon::parse($timbra_init)->format('H:i:00');

            if ($out != null) {


                //  $rounded = date('i', round(strtotime($out)/30)*30);
                //  dd($rounded);
                $timbra_end = Carbon::parse($out)->format('H:i:00');

                // if($datainit=='2023-03-21') dd($timbra_end,$out);
                $id_timbra = $timbratura->id;
                //CERCO SE CI SONO RICHIESTE di permesso INCLUSE PER IL TURNO
                $cercorichiestepermesso = DettaglioRichiesta::query()
                    ->where('rapporto_id', $rapporto_id)
                    ->where('data_richiesta', $datainit)
                    ->where(function ($querywhere) use ($inizio_turno, $fine_turno) {
                        $querywhere->where('orada', '>=', $inizio_turno)
                            ->where('oraa', '<=', $fine_turno)
                            ->orWhere(function ($queryor) use ($inizio_turno, $fine_turno) {
                                $queryor->where('orada', '<=', $inizio_turno)
                                    ->where('oraa', '>=', $fine_turno);
                            });
                    })
                    ->where('stato_richiesta', 3)
                    ->with('richiesta.tipologiaPermesso')
                    ->whereHas('richiesta.tipologiaPermesso', function (Builder $query) {
                        $query->where('is_permesso', '=', 'Si');
                        //->orwhere('is_extra','=','Si');
                    })
                    ->get();


                //SE CI SONO RICHIESTE DI PERMESSO
                if (count($cercorichiestepermesso) > 0) {
                    //PERMESSI PRESENTI!!
                 //   if($datainit=='2023-04-27') dd($cercorichiestepermesso);
                    foreach ($cercorichiestepermesso as $permesso) {
//if($datainit=='2023-03-01') dd(Carbon::parse($permesso->orada)->format('H:i:s'));
                        if (Carbon::parse($permesso->orada)->format('H:i') == Carbon::parse($inizio_turno)->format('H:i')) {
                           // if($datainit=='2023-04-27') dd($permesso);
                            $inizio_turno = $permesso->oraa;
                            $step_turno = $this->timeSteps($step, $inizio_turno, $fine_turno, 0);
                            array_push($query, ['start_time' => $permesso->orada, 'end_time' =>  $permesso->oraa, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'giustificate', 'dettagli_richiesta_id' =>  $permesso->id, 'tipo_richiesta' =>  $permesso->richiesta->tipologiaPermesso->name,'dove'=>'']);

                            //if($datainit=="2023-04-27") dd($step_turno);
                        } else if (Carbon::parse($permesso->oraa)->format('H:i:s') == Carbon::parse($fine_turno)->format('H:i:s')) {

                            $fine_turno = $permesso->orada;
                            $step_turno = $this->timeSteps($step, $inizio_turno, $fine_turno, 0);
                            array_push($query, ['start_time' => $permesso->orada, 'end_time' =>  $permesso->oraa, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'giustificate', 'dettagli_richiesta_id' =>  $permesso->id, 'tipo_richiesta' =>  $permesso->richiesta->tipologiaPermesso->name,'dove'=>'']);

                        } else {
                            /*       if($timbra_init<=Carbon::parse($permesso->orada)->format('H:i:s')){
                                       $this->timeSteps($step, $inizio_turno, $permesso->orada, 0);
                                   }
                                   else    $this->timeSteps($step, Carbon::parse($permesso->oraa)->format('H:i:s'), $fine_turno, 0);*/
                            //$step_turno = $this->timeSteps($step, $inizio_turno, $fine_turno, 0);
                            //Modifica aggiunta 07042023 per contemplare i permessi a mezzo turno
                            $step_turno=array_merge($this->timeSteps($step, $inizio_turno, $permesso->orada, 0),$this->timeSteps($step, $permesso->oraa, $fine_turno, 0));
                            array_push($query, ['start_time' => $permesso->orada, 'end_time' =>  $permesso->oraa, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'giustificate', 'dettagli_richiesta_id' =>  $permesso->id, 'tipo_richiesta' =>  $permesso->richiesta->tipologiaPermesso->name,'dove'=>'']);



                        }
                        //   $step_turno = $this->timeSteps($step, $inizio_turno, $fine_turno, 0);

//if($datainit=="2023-02-22") dd($step_turno);

                    }

                }
                //if($datainit=="2023-04-27") dd($step_turno);

                // if($datainit=="2023-02-22") dd($step_turno);
                $start_time = new \Carbon\Carbon($inizio_turno);
                $end_time = new \Carbon\Carbon($timbra_init);
                //   if($datainit=="2023-02-27") dd($timbra_init,$inizio_turno);
                if ($timbra_init > $inizio_turno) {
                    $tempotolleranzacalcolato = $start_time->diffInMinutes($end_time);
                  //  dd($timbra_init,$tempotolleranzacalcolato);
                    if ($tempotolleranzacalcolato <= $tolleranza) {
                        $timbra_init = $inizio_turno;
                    }

                } else {
                    //QUI CERCO LE RICHIESTE SE CI SONO DALL'INIZIO DELLA TIMBRATURA ALLA INIZIO TURNO


                    $min_rosso = $timbra_init;
                    $max_rosso = $inizio_turno;
                    $timbra_init = $inizio_turno;
                    $cercorichiesterossi1 = DettaglioRichiesta::query()
                        ->where('rapporto_id', $rapporto_id)
                        ->where('data_richiesta', $datainit)
                        ->where(function ($querywhere) use ($min_rosso, $max_rosso) {
                            $querywhere->where('orada', '>=', $min_rosso)
                                ->where('oraa', '<=', $max_rosso);
                            /*->orWhere(function ($queryor) use ($min_rosso, $max_rosso) {
                                $queryor->where('orada', '<=', $min_rosso)
                                    ->where('oraa', '>=', $max_rosso);
                            });*/

                        })
                        ->with('richiesta.tipologiaPermesso')
                        ->where('stato_richiesta', 3)->get();
                    //dd($cercorichiesterossi1);

                    if (count($cercorichiesterossi1) > 0) {
//if($datainit=='2023-03-03') dd($cercorichiesterossi1);
                        //$non_cercare=true;
                        //     if($datainit=='2023-02-18') dd($cercorichiesterossi);
                        foreach ($cercorichiesterossi1 as $richiesteverdi) {
//dd($richiesteverdi);
                            // if($datainit=='2023-02-22') dd($cercorichiesterossi1);
                            $orada = new \Carbon\Carbon($richiesteverdi->orada);
                            $oraa = new \Carbon\Carbon($richiesteverdi->oraa);
                            $orada_rosso = new \Carbon\Carbon($min_rosso);
                            $inizio = Carbon::parse($orada)->format('H:i:s');
                            //$ora_a_rosso=$max_rosso->start;
                            $dettagli_richiesta_id = $richiesteverdi->id;
                            $tipo_richiesta = $richiesteverdi->richiesta->tipologiaPermesso->name;
                            if ($tipo_richiesta == "FORMAZIONE") $tipo_richiesta = "FORMAZIONE EXTRA";
                            $tempotolleranzacalcolato_per_rosso = $orada->diffInMinutes($orada_rosso);
                            if ($min_rosso > $inizio) {
                                if ($tempotolleranzacalcolato_per_rosso <= $tolleranza) {
                                    $inizio = Carbon::parse($orada)->format('H:i:s');
                                } else $inizio = $orada_rosso;
                            } else {
                                $inizio = Carbon::parse($orada)->format('H:i:s');
                            }
                            $ora_a = Carbon::parse($oraa)->format('H:i:s');
                            //           array_push($query_permessi, ['start_time' => $start, 'end_time' => $end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'giustificate', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta]);

                            array_push($query, ['start_time' => $inizio, 'end_time' => $ora_a, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'giustificate', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta,'dove'=>'']);


                        }

                    }else{
                        //ROSSO SOLO SE LA DIFFERENZA è > 030!!!!!!!
                        $orada = new \Carbon\Carbon($min_rosso);
                        $oraa = new \Carbon\Carbon($max_rosso);
                        $interval = $oraa->diffInMinutes($orada);

                       // $value = $interval->format('%H:%i:%s');
                        if($interval>30)
                      array_push($query, array('start_time' => $min_rosso, 'end_time' => $max_rosso, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'ingiustificate', 'dettagli_richiesta_id' => 0, 'tipo_richiesta' => '','dove'=>''));

                    }


                }


                /*  // Commento questa parte per test
                    if (($fine_turno <= $timbra_end)) {
                             if ($fine_turno <= $timbra_init) {

                                 $step_merge = $this->timeSteps($step, $timbra_init, $timbra_end);
                             }
                             else
                             {

                                 $step_merge = $this->timeSteps($step, $timbra_init, $fine_turno);

                             }
                         } else {
                             if ($inizio_turno >= $timbra_init) {
                                 $step_merge = $this->timeSteps($step, $timbra_init, $timbra_end);
                             } else {
                                 //INSERIRE TOLLERANZA DI MINUTI
                                       $step_merge = $this->timeSteps($step, $timbra_init, $timbra_end);
                             }
                         }*/
                $time = strtotime($out);
                $timbra_end = date('H:i', floor($time / (30 * 60)) * (30 * 60));
                //$minutes = $time % 1080; # pulls the remainder of the hour.

                //  $time -= $minutes; # just start off rounded down.
                //dd($time,$minutes,Carbon::parse($time)->format('H:i'));
                //if ($minutes >= 1800) $time += 3600; # add one hour if 30 mins or higher.

                //$etime = date("gA", $time);
                //dd(Carbon::parse($time)->format('H:i'));
                $timbra_end = Carbon::parse($timbra_end)->format('H:i:00');
                // if($datainit=="2023-03-01") dd($timbra_end);
//dd($timbra_end);
                //$timbra_end = date('H:i:s', round(strtotime($timbra_end)/30*60)*30*60);
                // dd(Carbon::parse($timbra_end)->format('H:i'));
                if ($timbra_end > $fine_turno) {
                    $step_merge = $this->timeSteps($step, $timbra_init, $fine_turno);

                    //Step extra turno da controllare
                    $altrorossoulteriore = $this->timeSteps($step, $fine_turno, $timbra_end);
                   // dd($altrorossoulteriore);
                } else
                    $step_merge = $this->timeSteps($step, $timbra_init, $timbra_end);
              //  if($datainit=='2023-04-04') dd($step_merge,$timbra_init,$timbra_end);
               //if($datainit=="2023-04-28") dd($step_merge);
                array_push($step_finale_merge, $step_merge);
                //STEP FINALE MERGE ATTULAMENTE SEGNA LA REALE TIMBRATURA
                //  if($datainit=='2023-02-27' ) dd($step_finale_merge);
                $step_completo_merge = array_merge($step_completo_merge, $this->timeStepswitId($step, $timbra_init, $timbra_end, $id_timbra));

                if (count($step_turno) > count($step_merge)) {
//SE è MAGGIORE IL TURNO RISPETTO ALLA TIMBRATURA SIGNIFICA CHE MANCA QUALCOSA NELLA TIMBRATURE
                    //rosso QUINDI RAPPRESENTA LA DIFFERENZA DI TEMPO TRA LO STEP TURNO E LO STEP TIMBRA
                    $rosso = array_diff(array_map('json_encode', $step_turno), array_map('json_encode', $step_merge));
                    //aLTRIMENTI IL CONTRARIO ROSSO è EXTRA TURNO
                } else $rosso = array_diff(array_map('json_encode', $step_merge), array_map('json_encode', $step_turno));
                $rosso = array_map('json_decode', $rosso);
//                $timbra_end
//if($datainit=='2023-04-04') dd($rosso);
                           //     if($datainit=="2023-04-28") dd($rosso);
                $min_rosso = reset($rosso);
                $max_rosso = end($rosso);

                //  dd($min_rosso,$max_rosso);
                if (!empty($rosso)) {

                    try {
                        $cercorichiesterossi = DettaglioRichiesta::query()
                            ->where('rapporto_id', $rapporto_id)
                            ->where('data_richiesta', $datainit)
                            ->where(function ($querywhere) use ($min_rosso, $max_rosso) {
                                $querywhere->where('orada', '>=', $min_rosso->start)
                                    ->where('oraa', '<=', $max_rosso->end)
                                    ->orWhere(function ($queryor) use ($min_rosso, $max_rosso) {
                                        $queryor->where('orada', '<=', $min_rosso->start)
                                            ->where('oraa', '>=', $max_rosso->end);
                                    });

                            })
                            ->whereHas('richiesta.tipologiaPermesso', function (Builder $query) {
                                $query->where('is_giustificativo', '<>', 'Si');
                                //->orwhere('is_extra','=','Si');
                            })
                            ->with('richiesta.tipologiaPermesso')
                            ->where('stato_richiesta', 3)->get();


                    } catch (Throwable $e) {
                        report($e);

                        return false;
                    }
               //     if($datainit=='2023-03-20') dd($cercorichiesterossi);
                    if (count($cercorichiesterossi) > 0) {
                       //   if($datainit=='2023-03-20') dd($cercorichiesterossi);
                        $non_cercare = true;

                        foreach ($cercorichiesterossi as $richiesteverdi) {
//dd($richiesteverdi);
                            $orada = new \Carbon\Carbon($richiesteverdi->orada);
                            $oraa = new \Carbon\Carbon($richiesteverdi->oraa);
                            $orada_rosso = new \Carbon\Carbon($min_rosso->start);
                            $inizio = Carbon::parse($orada)->format('H:i:s');
                            //$ora_a_rosso=$max_rosso->start;
                            $dettagli_richiesta_id = $richiesteverdi->id;
                            $tipo_richiesta = $richiesteverdi->richiesta->tipologiaPermesso->name;
                            $tempotolleranzacalcolato_per_rosso = $orada->diffInMinutes($orada_rosso);
                            if ($min_rosso->start > $inizio) {
                                if ($tempotolleranzacalcolato_per_rosso <= $tolleranza) {
                                    $inizio = Carbon::parse($orada)->format('H:i:s');
                                } else $inizio = $orada_rosso;
                            } else {
                                $inizio = Carbon::parse($orada)->format('H:i:s');
                            }
                            $ora_a = Carbon::parse($oraa)->format('H:i:s');
                            //           array_push($query_permessi, ['start_time' => $start, 'end_time' => $end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'giustificate', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta]);
                            //VERIFICO SE CI SONO GIa' inserite le richieste giustificate nello slot timbra
                            //devo verificare su $query
                            $gia_esiste = false;
                            foreach ($query as $single) {
                                if (in_array($dettagli_richiesta_id, $single)) {
                                    $gia_esiste = true;
                                }
                            }

                            if ($gia_esiste == false){
                                //if($datainit=='2023-03-20') dd($inizio, $ora_a);
                                $step_richiesta = $this->timeSteps($step, Carbon::parse($inizio)->format('H:i:s'), $ora_a);
                            if (count($step_turno) == count($step_richiesta) && $tipo_richiesta == "FORMAZIONE") {
                                array_push($query, ['start_time' => $inizio, 'end_time' => $ora_a, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'presenze', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta,'dove'=>'UFFICIO']);
                                $tutto_il_giorno = true;
                            } else  array_push($query, ['start_time' => $inizio, 'end_time' => $ora_a, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'giustificate', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta,'dove'=>'']);
                            // dd($query);

                        }
                        }

                    } else {
                        //Cerco richieste per TUTTO IL GIORNO
                        $cercorichiesterossifull = DettaglioRichiesta::query()
                            ->where('rapporto_id', $rapporto_id)
                            ->where('data_richiesta', $datainit)
                            ->where(function ($querywhere) {
                                $querywhere->whereNull('orada')
                                    ->whereNull('oraa');

                            })
                            ->with('richiesta.tipologiaPermesso')
                            ->where('stato_richiesta', 3)->get();
                        //  if($datainit=='2023-03-03') dd($cercorichiesterossifull);
                        if (count($cercorichiesterossifull) > 0) {
                            foreach ($cercorichiesterossifull as $rich) {
                                $dettagli_richiesta_id = $rich->id;
                                $tipo_richiesta = $rich->richiesta->tipologiaPermesso->name;
                                array_push($query, array('start_time' => $min_rosso->start, 'end_time' => $max_rosso->end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'giustificate', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta,'dove'=>''));
                            }
                        } else {
                            //non ci sono richieste ma puo' essere la differenza rossa sbagliata e non consecutiva quindi devo togliere
                            //eventuale verde da tutto il rosso ed inserisco quello non giustificato

                            array_push($query, array('start_time' => $min_rosso->start, 'end_time' => $max_rosso->end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'assenze', 'dettagli_richiesta_id' => 0, 'tipo_richiesta' => '','dove'=>''));
                            //  if($datainit=='2023-02-24') dd(($query));
                        }
                    }

                }

                if ($tutto_il_giorno == false) {
                    //if($rosso)

                        $verde = array_diff(array_map('json_encode', $step_merge), array_map('json_encode', $rosso));
                        $verde = array_map('json_decode', $verde);
                        //if($datainit=="2023-04-04") dd($step_merge,$verde);
                        //   array_push($tot_verdi, $verde);
                        $max_verde = end($verde);
                        $min_verde = reset($verde);
                        if (!empty($verde)) {
                            $datitimbragiornata = Timbratura::query()
                                ->select("*",
                                    DB::raw('(CASE

                        WHEN timbrature.ip_address in (select ip_address from ipsedi) THEN "UFFICIO"
                        ELSE "SMARTWORKING" END ) as dove')
                                )
                                //where('user_id', '=', $userid)
                                ->where('id','=',$id_timbra)
                                ->orderBy('data_login')
                                ->first();
                            $dove=$datitimbragiornata->dove;
                            if($min_rosso>$min_verde && $max_rosso<$max_verde){

                             array_push($query, array('start_time' => $min_verde->start, 'end_time' => $min_rosso->start, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'presenze', 'dettagli_richiesta_id' => 0, 'tipo_richiesta' => '','dove'=>$dove));
                             array_push($query, array('start_time' => $max_rosso->end, 'end_time' => $max_verde->end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'presenze', 'dettagli_richiesta_id' => 0, 'tipo_richiesta' => '','dove'=>$dove));

                            }
                            else {
                            array_push($query, array('start_time' => $min_verde->start, 'end_time' => $max_verde->end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'presenze', 'dettagli_richiesta_id' => 0, 'tipo_richiesta' => '','dove'=>$dove));
                            }
                        }

                }
                //verifica se ci sono straordinari o extra fine timbra
                //ATTENZIONE TOLGO QUESTA CONdizione per verificare solo la singola
                //if (($fine_turno < $timbra_end) && ($fine_turno > $timbra_init)) {
                if ($fine_turno < $timbra_end){
              //  dd($fine_turno,$timbra_init);

                    $step_merge = $this->timeSteps($step, $fine_turno, $timbra_end);

                    array_push($step_finale_merge, $this->timeSteps($step, $timbra_init, $timbra_end));
                    $step_completo_merge = array_merge($step_completo_merge, $this->timeStepswitId($step, $timbra_init, $timbra_end, $id_timbra));

                    $rosso = array_diff(array_map('json_encode', $step_merge), array_map('json_encode', $step_turno));
                    $rosso = array_map('json_decode', $rosso);
//
                    $max_rosso = end($rosso);
                    $min_rosso = reset($rosso);

                    if ($non_cercare == false) {
                        $rosso = $altrorossoulteriore;
                       //    dd($rosso);
                        if (!empty($rosso))
                            //     if (!empty($verde)) {
                            //    if ($min_rosso->start != $min_verde->start && $max_rosso->end != $max_verde->end) {

                            //Cerco le richieste da min_rosso
                            $inizio_min_rosso = $min_rosso->start;
                        // if($datainit=='2023-02-02') dd($inizio_min_rosso);
                        $cercorichiestestrao = DettaglioRichiesta::query()
                            ->where('rapporto_id', $rapporto_id)
                            ->where('data_richiesta', $datainit)
                            ->where(function ($querywhere) use ($inizio_min_rosso) {
                                $querywhere->where('orada', '>=', $inizio_min_rosso);

                            })
                            ->where('stato_richiesta', 3)
                            ->with('richiesta.tipologiaPermesso')
                            ->whereHas('richiesta.tipologiaPermesso', function (Builder $query) {
                                $query->where('is_extra', '=', 'Si');
                                //->orwhere('is_extra','=','Si');
                            })
                            ->get();


                        if (count($cercorichiestestrao) > 0) {
                            //PERMESSI PRESENTI!!

                            foreach ($cercorichiestestrao as $strao) {
                                $orada = new \Carbon\Carbon($strao->orada);
                                $oraa = new \Carbon\Carbon($strao->oraa);
                                $min_rosso = new \Carbon\Carbon($strao->orada);
                                $max_rosso = new \Carbon\Carbon($strao->oraa);
                                $oraa = Carbon::parse($oraa)->format('H:i:s');
                                $orada = Carbon::parse($orada)->format('H:i:s');
                                $min_rosso = Carbon::parse($min_rosso)->format('H:i:s');
                                $max_rosso = Carbon::parse($max_rosso)->format('H:i:s');
                                $dettagli_richiesta_id = $strao->id;
                                $tipo_richiesta = $strao->richiesta->tipologiaPermesso->name;
                                //Rappresenta la richiesta di strao autorizzata
                                $step_merge_strao = $this->timeSteps($step, $orada, $oraa);
                                //  dd($step_merge_strao);
                                //rappresenta i rossi non ancora giustificati
                                $step_mancanti = $this->timeSteps($step, $min_rosso, $max_rosso);
                                //   if($datainit=='2023-02-22') dd($step_merge_strao,$step_mancanti);
                                if (count($step_merge_strao) > $step_mancanti) {
                                    $rossostrao = array_diff(array_map('json_encode', $step_merge_strao), array_map('json_encode', $step_mancanti));
                                    $rossost = array_map('json_decode', $rossostrao);
                                    $verde = array_diff(array_map('json_encode', $step_merge), array_map('json_encode', $rossost));
                                    $verde = array_map('json_decode', $verde);


                                } else {

                                    $rossostrao = array_diff(array_map('json_encode', $step_mancanti), array_map('json_encode', $step_merge_strao));
                                    $rossost = array_map('json_decode', $rossostrao);


                                    $verde = array_diff(array_map('json_encode', $step_mancanti), array_map('json_encode', $rossost));
                                    $verde = array_map('json_decode', $verde);
                                    //  if($datainit=='2023-02-22') dd($verde);
                                }
                                if (!empty($rossost)) {
                                    $max_rossost = end($rossost);
                                    $min_rossost = reset($rossost);
                                    // array_push($query, array('start_time' => $min_verdest->start, 'end_time' => $max_verdest->end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'presenze','dettagli_richiesta_id' => 0, 'tipo_richiesta' => ''));
                                    //INSERISCO IL NON GIUSTIFICATO IN ECCEDENZA
                                    array_push($query, array('start_time' => $min_rosso->start, 'end_time' => $max_rosso->end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'ingiustificate', 'dettagli_richiesta_id' => 0, 'tipo_richiesta' => '','dove'=>''));

                                }
                                if (!empty($verde)) {
                                    array_push($tot_verdi, $verde);
                                    $max_verde = end($verde);
                                    $min_verde = reset($verde);
                                    array_push($query, array('start_time' => $min_verde->start, 'end_time' => $max_verde->end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'giustificate', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta,'dove'=>''));

                                    //GIUSTIFICATO
                                }

                            }

                        } else {
                            array_push($query, array('start_time' => $min_rosso->start, 'end_time' => $max_rosso->end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'ingiustificate', 'dettagli_richiesta_id' => 0, 'tipo_richiesta' => '','dove'=>''));
                        }



                    }

                }


                //INSERISCI PAUSE

                $pause = Pausa::getpause($id_timbra)->get();
                if ($pause) {
                    foreach ($pause as $pausa) {
                        $inizo_pausa = Carbon::parse($pausa->inizio)->format('H:i:s');
                        $fine_pausa = Carbon::parse($pausa->fine)->format('H:i:s');
                        array_push($query, array('start_time' => $inizo_pausa, 'end_time' => $fine_pausa, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => $id_timbra, 'tipologia' => 'pause', 'dettagli_richiesta_id' => 0, 'tipo_richiesta' => '','dove'=>''));


                    }
                }
            } else $non_controllare = true;
        }
        if ($non_controllare == false) {
            $inizio_controllo = $inizio_turno;
//if($datainit=="2023-02-20") dd($tot_verdi);
            foreach ($tot_verdi as $finale) {

                if (!empty($finale)) {
                    $secondo = end($finale);

                    $secondo_formato = Carbon::parse($secondo->end)->format('H:i:s');

                    $calcola_slot = $this->timeSteps($step, $inizio_controllo, $secondo_formato);

                    $rimanenza = array_diff(array_map('json_encode', $step_turno), array_map('json_encode', $finale));
                    // if($datainit=="2023-02-15") dd($rimanenza);
                    $rimanenza = array_map('json_decode', $rimanenza);


                    $arancio = array_diff(array_map('json_encode', $calcola_slot), array_map('json_encode', $finale));
                    $arancio = array_map('json_decode', $arancio);
                    // if($datainit=="2023-02-15") dd($arancio);
                    $inizio_controllo = $secondo_formato;
                    $max_arancio = end($arancio);
                    $min_arancio = reset($arancio);
                    $esiste = false;
                    if (!empty($arancio)) {
                        foreach ($query as $qr) {
                            // dd($qr);
                            if (!in_array($min_arancio->start, $qr) && !in_array($max_arancio->end, $qr)) {

                                $esiste = true;
                            }

                        }

                        if ($esiste == false)
                            array_push($query, ['start_time' => $min_arancio->start, 'end_time' => $max_arancio->end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'assenze', 'dettagli_richiesta_id' => 0, 'tipo_richiesta' => '','dove'=>'']);
                    }
                    $max_turno = end($step_turno);

                    if ($secondo->end < $max_turno['end']) {
                        array_push($query, ['start_time' => $secondo->end, 'end_time' => $max_turno['end'], 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'assenze', 'dettagli_richiesta_id' => 0, 'tipo_richiesta' => '','dove'=>'']);
                        //if($datainit=='2023-04-04') dd("qui");

                    }

                }
            }
          //  if($datainit=='2023-04-04') dd($query);
//if($datainit=="2023-02-27") dd($query);
            SlotFinaleTurnoTimbra::insert($query);
            //   if($rapporto_id==130 && $datainit=='2023-02-02') dd($query);
            if ($controlla_richieste == "Siiiiiiiii") {
                $richiesteapprovate = DettaglioRichiesta::getrichiestegiornoapprovate($datainit, $rapporto_id)
                    ->with("richiesta.tipologiaPermesso")
                    ->whereHas('richiesta.tipologiaPermesso', function (Builder $query) {
                        $query->where('is_permesso', '=', 'Si');
                        //  ->orwhere('is_extra','=','Si');
                    })
                    ->get();
                //   if($datainit=='2023-02-24' && $rapporto_id=131) dd($richiesteapprovate);

                foreach ($richiesteapprovate as $richiesta) {
                    //  dd($richiesteapprovate);
                    $dettagli_richiesta_id = $richiesta->id;
                    $query_permessi = array();
                    $start = Carbon::parse($richiesta->orada)->format('H:i');
                    $end = Carbon::parse($richiesta->oraa)->format('H:i');

                    $tipo_richiesta = $richiesta->richiesta->tipologiaPermesso->name;
                    $is_giustificativo = $richiesta->richiesta->tipologiaPermesso->is_giustificativo;
                    if (!is_null($start)) {

                        if ($is_giustificativo == 'No') {
                            //   dd($dettagli_richiesta_id);
                            $update = SlotFinaleTurnoTimbra::query()
                                // ->where('user_id', $userid)
                                ->where('rapporto_id', $rapporto_id)
                                ->where('data', $datainit)
                                ->where('start_time', '>=', $start)
                                ->where('end_time', '>=', $end)
                                ->wherein('tipologia', ['assenze', 'ingiustificate'])
                                ->update(['tipologia' => 'giustificate', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'start_time' => $start, 'end_time' => $end, 'tipo_richiesta' => $tipo_richiesta]);
                        } else {
                            $update = SlotFinaleTurnoTimbra::query()
                                // ->where('user_id', $userid)
                                ->where('rapporto_id', $rapporto_id)
                                ->where('data', $datainit)
                                ->where('start_time', '>=', $start)
                                ->where('end_time', '>=', $end)
                                ->wherein('tipologia', ['assenze', 'ingiustificate'])
                                ->update(['tipologia' => 'presenze', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'start_time' => $start, 'end_time' => $end, 'tipo_richiesta' => $tipo_richiesta]);
                            //  dd($update);
                        }
                    } else {
                        $update = SlotFinaleTurnoTimbra::query()
                            //   ->where('user_id', $userid)
                            ->where('rapporto_id', $rapporto_id)
                            ->where('data', $datainit)
                            ->where('start_time', '>=', $inizio_turno)
                            ->where('end_time', '<=', $fine_turno)
                            ->wherein('tipologia', ['assenze', 'ingiustificate'])
                            ->update(['tipologia' => 'giustificate', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta]);

                    }
                    if ($update < 1) {
                        array_push($query_permessi, ['start_time' => $start, 'end_time' => $end, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'giustificate', 'dettagli_richiesta_id' => $dettagli_richiesta_id, 'tipo_richiesta' => $tipo_richiesta]);
                        SlotFinaleTurnoTimbra::insert($query_permessi);

                    }


                }
            }

        } else {
            array_push($query_assenza, ['start_time' => $inizio_turno, 'end_time' => $fine_turno, 'data' => $datainit, 'rapporto_id' => $rapporto_id, 'turno' => $id_turno, 'timbra_id' => 0, 'tipologia' => 'ingiustificate', 'dettagli_richiesta_id' => 0, 'tipo_richiesta' => '']);
            SlotFinaleTurnoTimbra::insert($query_assenza);
        }
        //CONTROLLO FINALE DEVO RIMUOVERE SE CI SONO INGIUSTIFICATE CHE COINCIDONO CON LE PRESENZE
        $selectpresenze = SlotFinaleTurnoTimbra::query()
            ->where('rapporto_id', $rapporto_id)
            ->where('data', $datainit)
            ->where('tipologia', 'presenze')
            ->get();

        foreach ($selectpresenze as $presente) {
            $init = $presente->start_time;
            $end = $presente->end_time;
            $selectcercaing = SlotFinaleTurnoTimbra::query()
                ->where('rapporto_id', $rapporto_id)
                ->where('data', $datainit)
                ->where('start_time', '>=', $init)
                ->where('end_time', '<=', $end)
                ->wherein('tipologia', ['ingiustificate','assenze'])
                ->delete();
        }
        $duplicates = SlotFinaleTurnoTimbra::select('tipologia', 'start_time','end_time','data','rapporto_id',DB::raw('COUNT(*) as count'))
            ->groupBy('tipologia', 'start_time','end_time','data','rapporto_id')
            ->having('count', '>', 1)
            ->get();
     //   if($datainit=="2023-04-04") dd($duplicates);
        $elimina="No";
        foreach ($duplicates as $duplicate) {
            $prendiid=SlotFinaleTurnoTimbra::where('tipologia', $duplicate->tipologia)
                ->where('start_time', $duplicate->start_time)
                ->where('end_time', $duplicate->end_time)
                ->where('data', $duplicate->data)
                ->where('rapporto_id',$duplicate->rapporto_id)->first();
                //->where('id','<>',$idd)

            $idd=$prendiid->id;
            $tipologia=$prendiid->tipologia;
            $start=$prendiid->start_time;
            $end=$prendiid->end_time;
            $data=$prendiid->data;
            $rapportoi=$prendiid->rapporto_id;
            $elimina="Si";

        }
     //   foreach ($duplicates as $duplicate) {
        if($elimina=="Si"){
            SlotFinaleTurnoTimbra::where('tipologia', $tipologia)
                ->where('start_time', $start)
                ->where('end_time',$end)
                ->where('data', $data)
                ->where('rapporto_id',$rapportoi)
              ->where('id','<>',$idd)
                ->orderBy('id', 'desc')

                ->delete();
        }
     //   }
      /*  $selectpresenze = SlotFinaleTurnoTimbra::query()
            ->where('rapporto_id', $rapporto_id)
            ->where('data', $datainit)
            ->where('tipologia', 'giustificate')
            ->get();
        foreach ($selectpresenze as $presente) {
            $init = $presente->start_time;
            $end = $presente->end_time;
            $id=$presente->id;
            $selectcercaing = SlotFinaleTurnoTimbra::query()
                ->where('rapporto_id', $rapporto_id)
                ->where('data', $datainit)
                ->where('start_time', '=', $init)
                ->where('end_time', '=', $end)
                ->where('id','<>',$id)
                ->wherein('tipologia', ['giustificate'])
                ->delete();
        }*/
if($tipo_contratto_ore>=8) {

    $update = SlotFinaleTurnoTimbra::query()
        //   ->where('user_id', $userid)
        ->where('rapporto_id', $rapporto_id)
        ->where('data', $datainit)
        ->where('tipologia','=','giustificate')
        ->where('tipo_richiesta', '=', 'SUPPLEMENTARE')
        ->update(['tipo_richiesta' => 'STRAORDINARIO']);
}




    }
