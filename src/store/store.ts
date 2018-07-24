import { fromJS } from 'immutable';
import { combineReducers } from 'redux';
import { middleware } from './index';
import * as login from './login';
import * as session from './session';
import * as matchs from './matchs';
import * as mesMatchs from './mesMatchs';
declare var ENV: string;
export interface IAppState {
  login?: login.ILogin;
  session?: session.ISession;
  matchs?: matchs.IMatchsDataRecord;
  mesMatchs?: mesMatchs.MesMatchsRecord
}
let a = require('redux-logger/dist/redux-logger.js');
const persistState = require('redux-localstorage');

export let enhancers = [
  persistState(
    '',
    {
      key: 'store',
      serialize: store => JSON.stringify(deimmutify(store)),
      deserialize: state => reimmutify(JSON.parse(state)),
    })
];
export const rootReducer = combineReducers<IAppState>({
  session: session.sessionReducer,
  login: login.loginReducer,
  matchs: matchs.MatchsReducer,
  mesMatchs: mesMatchs.MesMatchsReducer
});

export function deimmutify(store) {
  return {
    session: store.session.toJS(),
    login: store.login.toJS(),
    matchs: store.matchs.toJS(),
    mesMatchs: store.mesMatchs.toJS()
  };
}

export function reimmutify(plain) {
  return {
    session: session.SessionFactory(plain ? fromJS(plain.session) : null),
    login: login.LoginFactory(plain.login),
    matchs: matchs.MatchFactory((plain && plain.matchs) ? fromJS(plain.matchs) : null),
    mesMatchs: mesMatchs.mesMatchsFactory((plain && plain.mesMatchs) ? fromJS(plain.mesMatchs) : null)
  };
}

