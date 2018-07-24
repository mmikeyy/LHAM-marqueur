/**
 * Created by micra on 2016-09-21.
 */
import { NgRedux } from '@angular-redux/store';
import { Injectable } from '@angular/core';
import { IAppState } from '../store';
import { SessionActions } from '../session/session.actions';

@Injectable()
export class LoginActions {
  static LOGIN_DIAL = 'LOGIN_DIAL';
  static LOGIN_MDP_PERDU_DIAL = 'LOGIN_MDP_PERDU_DIAL';
  static LOGIN_NOUVEAU_MDP_DIAL = 'LOGIN_NOUVEAU_MDP_DIAL';
  static LOGIN_DISPLAY_STATUS = 'LOGIN_DISPLAY_STATUS';
  static LOGIN_LOGGING_OUT = 'LOGIN_LOGGING_OUT';
  static LOGIN_LOGOUT_COMPLETED = 'LOGIN_LOGOUT_COMPLETED';
  static LOGIN_LOGOUT_ERROR = 'LOGIN_LOGOUT_ERROR';
  static LOGIN_CHG_DONNEES = 'LOGIN_CHG_DONNEES';
  static LOGIN_SUBMIT = 'LOGIN_SUBMIT';
  static LOGIN_PERDU_MDP = 'LOGIN_PERDU_MDP';
  static LOGIN_COMPLETED = 'LOGIN_COMPLETED';
  static LOGIN_CODE_MDP_PERDU_ENVOYE = 'LOGIN_CODE_MDP_PERDU_ENVOYE';
  static LOGIN_CHANGER_MDP = 'LOGIN_CHANGER_MDP';
  static LOGIN_STORE_CODE_VALIDATION = 'LOGIN_STORE_CODE_VALIDATION';
  static LOGIN_CHANGER_PSEUDO = 'LOGIN_CHANGER_PSEUDO';

  constructor(private store: NgRedux<IAppState>) {
  }

  dialOpen() {
    this.store.dispatch({type: LoginActions.LOGIN_DIAL});
  }

  dialClose() {
    this.store.dispatch({type: LoginActions.LOGIN_DISPLAY_STATUS});
  }

  login(username, password) {
    this.store.dispatch({
      type: LoginActions.LOGIN_SUBMIT,
      payload: {userName: username, password}
    });

  }

  logout() {
    this.store.dispatch({
      type: LoginActions.LOGIN_LOGGING_OUT // voir epics
    });
    this.store.dispatch({
      type: SessionActions.LOGOUT_USER
    });
  }

  chgDonnees() {
    this.store.dispatch({
      type: LoginActions.LOGIN_CHG_DONNEES
    });
  }

  perduMdp() {
    this.store.dispatch({
      type: LoginActions.LOGIN_PERDU_MDP
    });
  }

  perduMdpEnvoye_adr(adresse: string = '', code?: number) {
    let action = {
      type: LoginActions.LOGIN_CODE_MDP_PERDU_ENVOYE,
      payload: {
        adresse,
        code
      }
    };
    this.store.dispatch(action);
  }

  storeCodeCalidation(code: number) {
    this.store.dispatch({
      type: LoginActions.LOGIN_STORE_CODE_VALIDATION,
      payload: {
        code
      }
    });

  }

  changerMdp() {
    this.store.dispatch({
      type: LoginActions.LOGIN_CHANGER_MDP
    });
  }

  changerPseudo(pseudo: string) {
    this.store.dispatch({
      type: LoginActions.LOGIN_CHANGER_PSEUDO,
      payload: {
        pseudo
      }
    });
  }

}
