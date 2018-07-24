import { TypedRecord } from 'typed-immutable-record';
import { Map, List } from 'immutable';
export interface ISessionRecord extends TypedRecord<ISessionRecord>,
  ISession {
}
export interface ISession {
  token: string;
  user: IUserRecord;
  hasError: boolean;
  isLoading: boolean;
  sel: string;
  errMsg: string;
  sessId: string;
}
export interface IUser {
  id: string;
  is_editeur: boolean;
  nom: string;
  perm_admin: boolean;
  superhero: boolean;
  perm_resultats: boolean;
  perm_horaire: boolean;
  perm_controleur: boolean;
  is_marqueur: boolean;
  is_arbitre: boolean;
}

export interface IPubDataRecord extends TypedRecord<IPubDataRecord>, IPubData {
}

export interface IPubData {
  id_annonceur: string;
  nom_entreprise: string;
  programmes: { type: string, widget: string }[];
}

export interface IUserRecord extends TypedRecord<IUserRecord>, IUser {
}



