import { Injectable } from '@angular/core';
import { NgRedux } from '@angular-redux/store';
import { ToastController } from 'ionic-angular';
import { IAppState } from '../store';

@Injectable()
export class MesMatchsActions {
  static MESMATCHS_GET_ALL = 'MESMATCHS_GET_ALL';
  static MESMATCHS_MERGE = 'MESMATCHS_MERGE';
  static MESMATCHS_GET_MORE = 'MESMATCHS_GET_MORE';
  static MESMATCHS_RESET = 'MESMATCHS_RESET';
  static MESMATCHS_SELECT_MATCH = 'MESMATCHS_SELECT_MATCH';
  static MESMATCHS_UPDATE_MATCH = 'MESMATCHS_UPDATE_MATCH';
  static MESMATCHS_SAUVEGARDER_POINTAGE = 'MESMATCHS_SAUVEGARDER_POINTAGE';
  static MESMATCHS_SAUVEGARDER_POINTAGE_SRV = 'MESMATCHS_SAUVEGARDER_POINTAGE_SRV';
  static MESMATCHS_SAVE_JOUEURS = 'MESMATCHS_SAVE_JOUEURS';
  static MESMATCHS_SAVE_FM = 'MESMATCHS_SAVE_FM';
  static MESMATCHS_SAVE_JOUEURS_FM = 'MESMATCHS_SAVE_JOUEURS_FM';
  static MESMATCHS_SAVE_EV = 'MESMATCHS_SAVE_EV';
  static MESMATCHS_DELETE_EV = 'MESMATCHS_DELETE_EV';
  static MESMATCHS_REFRESH_AN_DN = 'MESMATCHS_REFRESH_AN_DN'; // -> epic
  static MESMATCHS_UPDATE_AN_DN = 'MESMATCHS_UPDATE_AN_DN'; // rafraichit store
  static MESMATCHS_GET_FUSILLADE = 'MESMATCHS_GET_FUSILLADE'; // -> epic
  static MESMATCHS_UPDATE_RONDE = 'MESMATCHS_UPDATE_RONDE'; // -> epic
  static MESMATCHS_CHANGE_BUT = 'MESMATCHS_CHANGE_BUT';
  static MESMATCHS_SAVE_FUSILLADE = 'MESMATCHS_SAVE_FUSILLADE';



  constructor(private store: NgRedux<IAppState>) {

  }

  getAll() {
    this.store.dispatch({type: MesMatchsActions.MESMATCHS_GET_ALL});
  }

  getMore() {
    this.store.dispatch({type: MesMatchsActions.MESMATCHS_GET_MORE});
  }

  merge(data) {
    this.store.dispatch({
      type: MesMatchsActions.MESMATCHS_MERGE,
      payload: data
    })
  }

  reset() {
    this.store.dispatch({type: MesMatchsActions.MESMATCHS_RESET})
  }

  selectMatch(idMatch: string) {
    this.store.dispatch({
      type: MesMatchsActions.MESMATCHS_SELECT_MATCH,
      payload: idMatch
    })
  }

  updateMatch(idMatch: string, data) {
    this.store.dispatch({
      type: MesMatchsActions.MESMATCHS_UPDATE_MATCH,
      payload: {
        idMatch,
        data
      }
    });
  }

  updatePointage(idMatch: string, pts1: number, pts2: number) {
    this.store.dispatch({
      type: MesMatchsActions.MESMATCHS_SAUVEGARDER_POINTAGE_SRV,
      payload: {
        idMatch,
        pts1,
        pts2
      }
    });
  }

  getFeuilleMatch() {

  }

  saveJoueurs(liste) {
    this.store.dispatch({
        type: MesMatchsActions.MESMATCHS_SAVE_JOUEURS,
        payload: liste
      }
    )
  }

  saveFM(idMatch, liste) {
    this.store.dispatch({
        type: MesMatchsActions.MESMATCHS_SAVE_FM,
        payload: {idMatch, liste}
      }
    )
  }

  saveJoueursFM(idMatch, joueurs, fm) {
    this.store.dispatch({
        type: MesMatchsActions.MESMATCHS_SAVE_JOUEURS_FM,
        payload: {idMatch, joueurs, fm}
      }
    )
  }

  saveEvent(ev) {
    this.store.dispatch({
      type: MesMatchsActions.MESMATCHS_SAVE_EV,
      payload: ev
    });
  }

  deleteEvent(ev) {
    this.store.dispatch({
      type: MesMatchsActions.MESMATCHS_DELETE_EV,
      payload: ev
    })
  }

  updateAnDn(idMatch: string, an: string[], dn: string[]) {
    this.store.dispatch(({
      type: MesMatchsActions.MESMATCHS_UPDATE_AN_DN,
      payload: {idMatch, an, dn}
    }));
  }

  getFM(idMatch, idFM) {
    this.store.dispatch({
      type: MesMatchsActions.MESMATCHS_GET_FUSILLADE,
      payload: {
        idMatch,
        idFM,
        succes: () => console.log('ok')
      }
    })
  }

  updateRonde(ronde, liste) {
    this.store.dispatch({
      type: MesMatchsActions.MESMATCHS_UPDATE_RONDE,
      payload: {
        ronde,
        liste
      }
    })
  }

  changeBut(idJoueur, ronde, noEq, but) {
    this.store.dispatch({
      type: MesMatchsActions.MESMATCHS_CHANGE_BUT,
      payload: {idJoueur, ronde, noEq, but}
    })
  }

  saveFusillade(id_match, id_fm, liste) {
    this.store.dispatch({
      type: MesMatchsActions.MESMATCHS_SAVE_FUSILLADE,
      payload: {
        id_match,
        id_fm,
        liste
      }
    })
  }
}