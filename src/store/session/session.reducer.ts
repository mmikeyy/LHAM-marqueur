import { SessionActions } from './session.actions';
import { ISessionRecord } from './session.types';
import {
  INITIAL_STATE,
  INITIAL_USER_STATE,
  UserFactory
} from './session.initial-state';
import { LoginActions } from '../login/login.actions';
import { fromJS } from 'immutable';
import { IPayloadAction } from '../types';

export function sessionReducer(state: ISessionRecord = INITIAL_STATE,
                               action: IPayloadAction): ISessionRecord {

  switch (action.type) {
    case LoginActions.LOGIN_SUBMIT:
      return state.merge({
        token: null,
        user: INITIAL_USER_STATE,
        hasError: false,
        isLoading: true
      });

    case SessionActions.LOGIN_USER_SUCCESS:
      let s = state.merge({
        token: action.payload.token,
        user: UserFactory(fromJS(action.payload)),
        hasError: false,
        isLoading: false
      });
      console.log(s.toJS());
      return s;

    case SessionActions.LOGIN_USER_ERROR:
      return state.merge({
        token: null,
        user: INITIAL_USER_STATE,
        hasError: true,
        isLoading: false
      });

    case SessionActions.SESSION_SET_SALT:
      return state.set('sel', action.payload.sel);

    case SessionActions.SESSION_ERROR:
      let pl = action.payload;

      return state.merge({
        errMsg: pl.msg + (pl.ref ? ` (${pl.ref})` : ''),
        hasError: true,
        isLoading: false
      });
    case SessionActions.LOGOUT_USER:
      let sel = state.get('sel');
      let sessId = state.get('sessId');
      state = INITIAL_STATE;
      return state.merge({
        sel,
        sessId
      });

    case SessionActions.SESSION_CLR_ERROR:
      return state.merge({
        hasError: false,
        errMsg: ''
      });

    case SessionActions.SESSION_SET_SALT_SESSID:
      // console.log('set salt sessid', action.payload);
      return state.merge({
        sel: action.payload.sel,
        sessId: action.payload.sessId
      });
    case SessionActions.SESSION_INIT:
      return state.merge({
        hasError: false,
        errMsg: '',
        isLoading: false
      });

    case SessionActions.SESSION_SET:
      return state.merge(action.payload);

    default:
      return state;
  }
}
