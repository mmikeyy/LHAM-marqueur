import { Injectable } from '@angular/core';
import { Observable } from 'rxjs/Observable';

import 'rxjs/add/observable/of';
import 'rxjs/add/operator/mergeMap';
import 'rxjs/add/operator/filter';
import 'rxjs/add/operator/catch';
import { LoginActions } from '../store/login/login.actions';
import { XhrService } from '../services/xhr.service';
import { GrowlService } from '../services/growl.service';
import { SessionActions } from '../store/session/session.actions';
import { IPayloadAction } from '../store/types';
import { LoginService } from '../services/login.service';

const BASE_URL = '/api';

@Injectable()
export class SessionEpics {
  constructor(
              private xhr: XhrService,
              private growl: GrowlService,
              private loginServ: LoginService
  ) {
  }

  login(action$: Observable<IPayloadAction>) {

    return action$.filter(({type}) => {

      // let type = aa['type'];
      return type === LoginActions.LOGIN_SUBMIT;
    })
      .mergeMap(({payload}) => {
        return this.xhr.post(
          'login_visiteur',
          'login',
          {
            id: payload.userName,
            mdp: payload.password
          }, {observableResult: true})
          .map(
            (data) => {
              setTimeout(() => this.loginServ.emitChangeEvent());
              return ({
                type: SessionActions.LOGIN_USER_SUCCESS,
                payload: data
              });
            }
          )
          .catch(
            data => Observable.of({
              type: SessionActions.SESSION_ERROR,
              payload: data
            })
          );
      });
  }

  getSalt(action$: Observable<IPayloadAction>) {
    return action$.filter(({type}) => type === SessionActions.SESSION_GET_SALT)
      .flatMap(({payload}) => {
        console.log('epics get salt');
        return this.xhr.get('pageData', 'get_sel', {}, {observableResult: true})
          .map(
            data => ({
              type: SessionActions.SESSION_SET_SALT,
              payload: data
            })
          )
          .catch(
            data => {
              return Observable.of({
                type: SessionActions.SESSION_ERROR,
                payload: data
              });
            }
          );
      });
  }

  // logout(action$: Observable<IPayloadAction>) {
  //   return action$.filter(({type}) => type === SessionActions.LOGOUT_USER)
  //     .flatMap<IPayloadAction>(() => {
  //       return this.xhr.get('login_visiteur', 'logout', {}, {observableResult: true})
  //         .map(
  //           () => {
  //             this.growl.info('OK');
  //             return Observable.of({type: 'NOOP'});
  //           }
  //         )
  //         .catch(
  //           (data) => {
  //             this.growl.xhr_error(data);
  //
  //             return Observable.of({
  //               type: SessionActions.SESSION_ERROR,
  //               payload: data
  //             });
  //           }
  //         );
  //     });
  // }

  getSaltSessId(action$: Observable<IPayloadAction>) {
    return action$.filter(({type}) => type === SessionActions.SESSION_GET_SALT_SESSID)
      .flatMap(() => {
        return this.xhr.get('pageData', 'get_salt_sessid', {}, {observableResult: true})
          .map(
            (data) => {
              return {
                type: SessionActions.SESSION_SET_SALT_SESSID,
                payload: data
              };
            }
          )
          .catch(
            (data) => {
              this.growl.xhr_error(data);
              return Observable.of({
                type: SessionActions.SESSION_ERROR,
                payload: data
              });
            }
          );
      });
  }

  logout(action$: Observable<IPayloadAction>) {
    return action$.filter(({type}) => type === SessionActions.LOGOUT_USER)
      .flatMap(() => {
        return this.xhr.get('login_visiteur', 'logout', {}, {observableResult: true})
          .map(
            () => ({
              type: LoginActions.LOGIN_DISPLAY_STATUS
            })
          )

          .catch(
            (data) => {
              this.growl.xhr_error(data);
              return Observable.of({
                type: SessionActions.SESSION_ERROR,
                payload: data
              });
            }
          );
      });
  }

  sessionError(action$: Observable<IPayloadAction>) {
    return action$.filter(({type}) => type === SessionActions.SESSION_ERROR)
      .flatMap(({payload}) => {
        this.growl.xhr_error(payload);
        return Observable.of({
          type: 'NOOP'
        });
      });
  }

}
