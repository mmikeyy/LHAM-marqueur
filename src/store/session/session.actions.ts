import {Injectable} from '@angular/core';
import {NgRedux} from '@angular-redux/store';
import {IAppState} from '../store';
import {UserFactory} from './session.initial-state';
import { fromJS } from 'immutable';

@Injectable()
export class SessionActions {
  static LOGIN_USER = 'LOGIN_USER';
  static LOGIN_USER_SUCCESS = 'LOGIN_USER_SUCCESS';
  static LOGIN_USER_ERROR = 'LOGIN_USER_ERROR';
  static LOGOUT_USER = 'LOGOUT_USER';
  static SESSION_GET_SALT = 'SESSION_GET_SALT';
  static SESSION_SET_SALT = 'SESSION_SET_SALT';
  static SESSION_ERROR = 'SESSION_ERROR';
  static SESSION_CLR_ERROR = 'SESSION_CLR_ERROR';
  static SESSION_GET_SALT_SESSID = 'SESSION_GET_SALT_SESSID';
  static SESSION_SET_SALT_SESSID = 'SESSION_SET_SALT_SESSID';
  static SESSION_INIT = 'SESSION_INIT';
  static SESSION_SET = 'SESSION_SET';
  
  constructor(private ngRedux: NgRedux<IAppState>) {
  }
  
  loginUser(credentials) {
    // console.log('toto');
    
    this.ngRedux.dispatch({
      type: SessionActions.LOGIN_USER,
      payload: credentials,
    });
  };
  
  logoutUser() {
    this.ngRedux.dispatch({type: SessionActions.LOGOUT_USER});
  };
  
  ensureSalt() {
    let sel = this.ngRedux.getState().session.sel;
    let sessId = this.ngRedux.getState().session.sessId;
    if (!sel || !sessId) {
      this.ngRedux.dispatch({type: SessionActions.SESSION_GET_SALT_SESSID});
    }
    
  }
  
  getSaltSessionId() {
    this.ngRedux.dispatch({type: SessionActions.SESSION_GET_SALT_SESSID});
  }
  
  clrError() {
    this.ngRedux.dispatch({type: SessionActions.SESSION_CLR_ERROR});
  }
  
  init() {
    this.ngRedux.dispatch({
      type: SessionActions.SESSION_INIT
    });
    
  }
  
  update(data) {
    // console.log('update avec data', data);
    this.ngRedux.dispatch({
      type: SessionActions.SESSION_SET,
      payload: {
        token: '',
        user: UserFactory(fromJS(data.login_info)),
        hasError: false,
        isLoading: false,
        sel: data.sel,
        errMsg: '',
        sessId: data.sessionId
      }
    });
  }
  
} 
