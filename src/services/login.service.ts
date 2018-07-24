/**
 * Created by micra_000 on 2016-04-25.
 */

import { Injectable, EventEmitter } from '@angular/core';
import { ActivatedRouteSnapshot } from '@angular/router';
import * as moment from 'moment';
import { LoginActions } from '../store/login/login.actions';
import { SessionActions } from '../store/session/session.actions';
import { loginStatusType } from '../types/login.status.type';
import { XhrService } from './xhr.service';
import { CookieService } from 'ngx-cookie';

import { Md5 } from 'ts-md5/dist/md5';
import { Observable } from 'rxjs/Rx';
import { GrowlService } from './growl.service';
import { NgRedux } from '@angular-redux/store';
import { IAppState } from '../store/store';


import * as _ from 'lodash';
// import * as R from 'ramda';

export interface ValidationRes { [key: string]: any; }

declare let $: any;

declare function require(string): any;

@Injectable()
export class LoginService {

  public userName: string;
  public password: string;
  public err_msg: string = '';

  public status_: loginStatusType = loginStatusType.loggedOut;

  public closeDialogs: EventEmitter<string> = new EventEmitter<string>(false);
  public loginChange: EventEmitter<object> = new EventEmitter<object>(false);
  public nbChangeEmits = 0;
  public failedRoute: ActivatedRouteSnapshot;

  private _data: {
    is_editeur?: any,
    id?: any,
    nom?: any,
    superhero?: boolean
  } = {};

  constructor(public xhr: XhrService,
              public growl: GrowlService,
              public cookies: CookieService,
              public store: NgRedux<IAppState>,
              public loginActions: LoginActions,
              public sessionActions: SessionActions
  ) {

    try {
      let $loginInfo = $('#login_info');
      if ($loginInfo.length) {

        this._data = JSON.parse($loginInfo.text());
        if (this._data && this._data.id) {
          this.status = loginStatusType.loggedIn;
        }

        setTimeout(() => {
          this.emitChangeEvent();
        }, 100);
      }
    } catch (err) {
      console.log('login non disponible...');
    }
    setTimeout(() => {
      sessionActions.ensureSalt();
    });

    this.closeDialogs.subscribe(() => {
      return true;
    });
  }

  emitChangeEvent() {
    this.loginChange.emit(this._data);
    this.nbChangeEmits++;
  }

  getDefaultPseudo() {
    // console.log(this.store.getState());
    let pseudo = this.store.getState().login.pseudo;
    if (pseudo) {
      return pseudo;
    }
    try {
      return this.cookies.get('pseudo');
    } catch (err) {
      return '';
    }
  }

  setDefaultPseudo(val: string) {
    this.cookies.put('pseudo', val, {
      expires: moment().add(6, 'months').toDate()
    });
  }

  clearErrMsg() {
    this.err_msg = null;
  }

  showStatus(delayed = true) {
    this.status = loginStatusType.transition;
    setTimeout(() => {
      if (this._data && this._data.id) {
        this.status = loginStatusType.loggedIn;
      } else {
        this.status = loginStatusType.loggedOut;
      }
    }, delayed ? 1000 : 0);
  }

  get nom_visiteur() {
    return this.store.getState().session.user.nom
  }

  get status() {
    return this.status_;
  }

  set status(val: loginStatusType) {
    this.status_ = val;

  }

  get loggedIn(): boolean {
    return !!this.store.getState().session.user.id;
  }

  get idVisiteur() {
    return this.store.getState().session.user.id;
  }

  logout() {
    this.loginActions.logout();
    this.emitChangeEvent();
  }

  login(userName: string, password: string) {

    let scrambledPassword = this.scramble(password);

    this.loginActions.login(userName, scrambledPassword);
  }

  setLoginData(data: object = {}) {

    if (typeof data !== 'object' || !data || !data['id'] || parseInt(data['id'], 10) === 0) {
      this._data = {};
    } else {
      this._data = data;
    }
    this.loginChange.emit(data);
  }

  get loginData() {
    return this._data;
  }

  scramble(pw: string): string {
    let state = this.store.getState();
    let salt = state.session.sel;
    let addition = state.session.sessId;
    if (!salt || !addition) {
      throw 'Donnée manquante pour traitement de mot de passe';
    }

    let sha1 = require('sha1');
    pw = sha1(salt + Md5.hashStr(pw));
    // console.log('session id = ', addition);
    addition += Date.today().toString('yyyyMMdd');
    pw += addition + String.fromCharCode(addition.length);

    let keyValues = [];
    Observable
      .range(0, 16)
      .map(() => Math.floor(Math.random() * 255))
      .subscribe(v => keyValues.push(v));

    let res = [];
    let repeated_keys = this.obtainKeys(keyValues, pw.length);
    Observable
      .concat(
        keyValues
        ,
        Observable.zip(
          Observable.from(repeated_keys)
            .map((v, ind) => ind % 2 === 0 ? (v << 1) : v >> 1)
          ,

          Observable.from(pw)
            .map((v) => v.charCodeAt(0))
          ,
          (a, b) => a ^ b
        )
      )
      .subscribe(v => res.push(('00' + v).substr(-3)));
    return res.join('');
  }

  obtainKeys(vals: number[], number: number): number[] {
    let res = [];
    while (number > 0) {
      res = res.concat(vals.slice(0, number));
      number -= vals.length;
    }
    return res;
  }

  unscramble(pwString: string): string {

    let pw: number[] = [];
    Observable
      .from(pwString)
      .bufferCount(3)
      .map(val => parseInt(val.join(''), 10))
      .toArray()
      .subscribe(v => pw = v)
    ;

    let res = '';
    let keys = [];
    Observable.from(pw)
      .take(16)
      .map((v, i) => (i % 2 === 0) ? (v << 1) : v >> 1)
      .subscribe(v => keys.push(v));

    let repeated_keys = this.obtainKeys(keys, pw.length - 16);

    Observable.zip(
      Observable.from(repeated_keys)
      ,
      Observable.from(pw).skip(16)
      ,
      (a, b) => a ^ b
    )
      .toArray()
      .subscribe(list => {
        let nb_added = list.pop();
        res = list
          .slice(0, list.length - nb_added)
          .map(v => String.fromCharCode(v))
          .join('');
      })
    ;
    return res;
  }

  validate_mdp(control): ValidationRes {
    let val = _.trim(control.value);
    if (val !== control.value) {
      control.setValue(val);
      return;
    }

    let result: ValidationRes = {};
    if (!/[A-Z]/.test(val)) {
      result['doit_inclure_maj'] = true;
    }
    if (!/[a-z]/.test(val)) {
      result['doit_inclure_min'] = true;
    }
    if (!/.*[0-9].*/.test(val)) {
      result['doit_inclure_chiffre'] = true;
    }
    if (!/[^0-9a-z]/i.test(val)) {
      result['doit_inclure_autre'] = true;
    }
    if (val.length < 6) {
      result['trop_court'] = true;
    }
    return result;
  }

  is(perm?: string): boolean {
    return !!this.store.getState().session.user.get(perm, false);
  }

  isOr(perms: string[]): boolean {
    for (let perm of perms) {
      if (this.is(perm)) {
        return true;
      }
    }
    return false;
  }

  hasRole(idEq, role, orAbove = false) {
    let user = this.store.getState().session.user;

    role += '';
    let fn = (tag) => {
      let list = user.get(tag);
      return list && list.indexOf(idEq) > -1;
    };
    if (role === '2') {
      return fn('eq_adj') || orAbove && (fn('eq_entr') || fn('eq_gerant'));
    } else if (role === '1') {
      return fn('eq_entr') || orAbove && fn('eq_gerant');
    } else {
      return fn('eq_gerant');
    }
  }

  // isGerant() {
  //   let user = this.store.getState().session.user;
  //   let list = user.get('eq_gerant');
  //   return list && list.length > 0;
  // }
  // isEntr() {
  //   let user = this.store.getState().session.user;
  //   let list = user.get('eq_entr');
  //   return list && list.length > 0;
  // }
  //
  // isRespGerEntr(idEq: string): boolean {
  //   if (!this.loggedIn) {
  //     this.growl.info('ouvrir_session');
  //     return false;
  //   }
  //   let user = this.store.getState().session.user;
  //   if (R.isNil(user.eq_resp_niv)) {
  //     this.growl.info('ouvrir_nouv_session');
  //     return false;
  //   }
  //   return !!(user.eq_gerant.includes(idEq) || user.eq_entr.includes(idEq) || user.eq_resp_niv.includes(idEq));
  // }

}
