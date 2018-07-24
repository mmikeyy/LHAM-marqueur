import {
  ISessionRecord,
  ISession,
  IUser,
  IUserRecord, IPubDataRecord
} from './session.types';
import { makeTypedFactory } from 'typed-immutable-record';
import { Map, List } from 'immutable';

export const UserFactory = makeTypedFactory<IUser, IUserRecord>({
  id: null,
  is_editeur: false,
  nom: null,
  perm_admin: false,
  superhero: false,
  perm_resultats: false,
  perm_horaire: false,
  perm_controleur: false,
  is_marqueur: false,
  is_arbitre: false,
});

export const INITIAL_USER_STATE = UserFactory();

export const SessionFactory = (val?) => {

  let session =
    makeTypedFactory<ISession, ISessionRecord>({
      token: null,
      user: INITIAL_USER_STATE,
      hasError: false,
      isLoading: false,
      sel: '',
      errMsg: '',
      sessId: null
    })(val);
  if (!val) {
    return session;
  }
  // transformer le champ user en immutable map; sinon serait un simple objet
  return session.merge({
    user: UserFactory(session.get('user'))
  });
};

export const INITIAL_STATE = SessionFactory();
