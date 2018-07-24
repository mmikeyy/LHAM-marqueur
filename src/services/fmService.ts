import { EventEmitter, Injectable } from '@angular/core';
import { Map } from 'immutable';
import { ToastController } from 'ionic-angular';
import { NgRedux, select } from '../../node_modules/@angular-redux/store/lib/src';
import { MesMatchsActions } from '../store/mesMatchs/mesMatchs.actions';
import { FeuilleMatch } from '../store/mesMatchs/mesMatchs.types';
import { IAppState } from '../store/store';
import { ContextCrudServ, CrudService } from './crud.service';
import { MatchsService } from './matchs.service';
import { XhrOptions, XhrService } from './xhr.service';
import * as R from 'ramda'
import * as Parallel from 'async-parallel';

@Injectable()
export class FmService {

  public stdXhrOpts: XhrOptions = {
    noThrow: true,
    displayError: true
  };

  public crudFM: ContextCrudServ;
  public periodeChoisie: number;
  public currentEv: FeuilleMatch;
  public typeSoumissionChrono = 'normal';
  public joueurs = {};
  public idMatch;
  public equipes: { id: string, nom: string }[] = [];
  public closeSlidingEvMenu = new EventEmitter();

  @select(['mesMatchs', 'joueurs']) $joueurs;

  @select(['mesMatchs', 'idMatchCharge']) $idMatch;

  constructor(
    public crudServ: CrudService,
    public xhr: XhrService,
    public mesMatchsAct: MesMatchsActions,
    public matchServ: MatchsService,
    public store: NgRedux<IAppState>,
    public toastCtrl: ToastController
  ) {
    this.crudFM = this.crudServ.setUrl('feuille_match');
    this.$joueurs.subscribe(joueurs => joueurs.forEach(j => this.joueurs[j.get('id')] = j.toJS()));
    this.$idMatch.subscribe(idMatch => {
      this.idMatch = idMatch;
      this.equipes = this.matchServ.equipesMatch(idMatch);
    });
  }

  async getFeuilleMatch(idMatch) {
    console.log('...............', idMatch);
    let feuille;
    let joueurs;
    await Parallel.invoke([
      async () => feuille = await this.crudFM.get(idMatch)
      ,
      async () => joueurs = await this.xhr.get('marqueur', 'get_joueurs', {
        id_match: idMatch,
        raw_list: true,
        process_list: true
      }, {displayError: true, noThrow: true})
        .then(data => {
          if (data.result) {
            return R.dissoc('result', data);
          } else {
            return null;
          }
        })
    ]);

    // console.log('feuille', feuille);
    // console.log('joueurs', joueurs);
    if (!joueurs || !feuille) {
      return false;
    }
    joueurs = R.pipe(
      R.prop('liste'),
      R.filter((val) => val['choisi'] || val['locked']),
      R.map(R.pipe(
        (val) => R.assoc('id_equipe', val['id_equipe_match'] || val['id_equipe'], val),
        R.pick(['id', 'nom', 'position', 'id_equipe', 'no_chandail'])
      ))
    )(joueurs as any);
    // this.mesMatchsAct.saveJoueurs(joueurs);
    // this.mesMatchsAct.saveFM(idMatch, feuille);

    this.mesMatchsAct.saveJoueursFM(idMatch, joueurs, feuille);

  }

  evURI(ev) {
    return ev.get('id_match') + '/' + ev.get('id');
  }

  async updateEv(ev, updates) {
    console.log('update event', updates);
    let updatedEv = await this.crudFM.patch(this.evURI(ev), updates, {noThrow: true, displayError: true});
    console.log('................', updatedEv);
    if (updatedEv) {
      this.mesMatchsAct.saveEvent(updatedEv);
    }
    return !!updatedEv;
  }

  chronoFinPeriode(ev?) {
    let periode;
    let idMatch;
    if (ev) {
      if (Map.isMap(ev)) {
        periode = ev.get('periode');
        idMatch = ev.get('id_match');
      } else {
        periode = ev.periode;
        idMatch = ev.id_match;
      }
    }
    let state = this.store.getState().mesMatchs;
    periode = periode || this.periodeChoisie;
    idMatch = idMatch || this.idMatch;
    if (!periode || !idMatch) {
      console.log('fin PPPPPPPPPPeriode nulle 1');
      return null;
    }
    let finPer = state.get('feuilleMatch').find(ev => ev.get('id_match') === idMatch && ev.get('periode') === periode && ev.get('type_enreg') === 'fin_periode');
    if (!finPer) {
      console.log('fin PPPPPPPPPPeriode nulle 2');
      return null;
    }
    console.log('fin PPPPPPPPPPeriode ', finPer.get('chrono'));
    return finPer.get('chrono')
  }

  secFinPeriode(ev?) {
    return this.chronoToSec(this.chronoFinPeriode(ev));
  }

  chronoToSec(chrono) {
    if (!/^\d\d:\d\d$/.test(chrono)) {
      return null;
    }
    return parseInt(chrono.match(/^\d\d/)) * 60 + parseInt(chrono.match(/\d\d$/))
  }


  async sauvegarderEv(ev) {
    console.log('sauvegarder', ev);
    let record;

    if (ev.id) {
      record = await this.crudFM.patch(ev.id_match + (ev.id ? '/' + ev.id : ''), ev)
    } else {
      record = await this.crudFM.post(ev.id_match, ev)
    }

    if (record) {
      console.log('record returned', record);
      this.mesMatchsAct.saveEvent(record);
      return true;
    } else {
      console.error('erreur');
    }
    return false;
  }

  async effacerEv(ev) {
    let id;
    let idMatch;
    if (Map.isMap(ev)) {
      id = ev.get('id');
      idMatch = ev.get('id_match');
    } else {
      id = ev.id;
      idMatch = ev.id_match;
    }
    let succes = await this.crudFM.delete(`${idMatch}/${id}`);
    if (succes) {
      this.mesMatchsAct.deleteEvent(ev);

    }
    return succes;
  }

  nomJoueur(id) {
    return R.pathOr('', [id, 'nom'], this.joueurs);
  }

  autreEquipe(id) {
    let autre = R.filter(eq => eq.id !== id, this.equipes);
    if (autre.length !== 1) {
      return null;
    }
    return autre[0].id;
  }

  nomEquipe(id) {
    if (!id) {
      return '';
    }
    let record = R.find(eq => eq.id === id, this.equipes);
    return record ? record.nom : '';
  }

  nomAutreEquipe(id) {
    return this.nomEquipe(this.autreEquipe(id));
  }

  nomEquipeNo(no) {
    return R.pathOr('?', [no - 1, 'nom'], this.equipes);
  }

  idEquipeNo(no) {
    return R.pathOr('?', [no - 1, 'id'], this.equipes);
  }

  async sauvegarderEvFusillade(id_match, id_fm, ev, fusillade) {
    let results = [];

    await Parallel.invoke([
      async () => {
        results[0] = ev ? await this.sauvegarderEv(ev) : true
      }
      ,
      async () => {
        if (!fusillade) {
          results[1] = true;
          return;
        }
        let data = await this.xhr.post('gestion_feuille_match', 'save_fusillade', {
          id_match,
          id_fm,
          data: R.pipe(
            R.dissoc('id'),
            JSON.stringify
          )(fusillade)
        }, this.stdXhrOpts);
        if (data.result) {
          this.mesMatchsAct.saveFusillade(id_match, id_fm, data.liste);
          results[1] = true;
          return;
        }
        results[1] = false;
      }
    ]);
    console.log('returning ', results);
    return results[0] && results[1];
  }

  async verifierForfait(idMatch, update = false) {
    if (!idMatch) {
      return;
    }
    let data = await this.xhr.get('marqueur', 'verifier_forfait', {
      id_match: idMatch,
      proceder: update ? 1 : 0
    }, this.stdXhrOpts);

    if (data.result) {

      if (update && data.data_match) {
        this.mesMatchsAct.updateMatch(idMatch, data.data_match);
      }
      return data;
    }
    return null;
  }

  async retirerForfait(idMatch) {
    if (!idMatch) {
      return;
    }
    let data = await this.xhr.get('marqueur', 'retirer_forfait', {
      id_match: idMatch
    }, this.stdXhrOpts);

    if (data.result) {
      if (data.data_match) {
        this.mesMatchsAct.updateMatch(idMatch, data.data_match);
      }
      return true;
    }
    return false;
  }


  toast(message, duration = 3000) {
    let toast = this.toastCtrl.create({
      message,
      duration
    });
    toast.present();
  }

  async effacerResultats(idMatch, stats_seulem = false) {
    if (!idMatch) {
      return;
    }
    let data = await this.xhr.get('marqueur', 'effacer_resultats_stats', {
      ref: idMatch,
      stats_seulem
    }, this.stdXhrOpts);

    if (data.result) {
      if (data.data_match) {
        this.mesMatchsAct.updateMatch(idMatch, data.data_match);
      }
      return true;
    }
    return false;
  }

  async collecterStats(id_match, sauvegarder) {
    let opts = R.merge(this.stdXhrOpts, {displayError: false});
    console.log(opts);
    let data = await this.xhr.get('marqueur', 'collecter_stats', {id_match, sauvegarder}, opts);
    if (!data.result) {
      this.toast('Échec - collection statistiques');
    } else if (data.data_match) {
      this.mesMatchsAct.updateMatch(id_match, data.data_match);
    }
    return data;
  }

  /*
  id: string;
  nom: string;
  id_match: string;
  position: number;
  id_equipe: string;
  no_chandail: string;
   */

}