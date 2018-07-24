import { Ord } from 'ramda';
import { TypedRecord } from 'typed-immutable-record';
import { MatchRecord } from '../matchs';
import { List, Map, OrderedMap } from 'immutable';
import * as R from 'ramda';

export interface MesMatchsRecord extends TypedRecord<MesMatchsRecord>, MesMatchs {
}

export interface MesMatchs {
  idMatch: string; // id du match sélectionné
  idMatchCharge: string; // feuille de match et liste de joueurs
  liste: Map<string, MatchRecord>;
  nb_non_charges: number;
  feuilleMatch: List<FeuilleMatchRecord>;
  joueurs: List<JoueurMatchRecord>;
  idFMFusilladeChargee: string;
  fusilladeSauvegardee: boolean;
  fusillade?: List<FusilladeRecord>;
}

export interface FeuilleMatchRecord extends TypedRecord<FeuilleMatchRecord>, FeuilleMatch {
}

export interface FeuilleMatch {
  id?: string;
  id_match: string;
  id_equipe?: string;
  id_membre?: string;
  periode: number;
  fin_periode?: boolean;
  chrono: string;
  duree_punition?: number;
  expulsion?: boolean;
  codes_punition?: string;
  but?: boolean;
  but_special?: string;
  but_propre_filet?: boolean;
  id_membre_passe?: string;
  id_membre_passe2?: string;
  passe_propre_filet?: boolean;
  retrait_gardien?: boolean;
  fin_retrait_gardien?: boolean;
  commentaire?: string;
  id_membre_gardien?: string;
  resultat?: number;
  resultat_adversaire?: number;
  resultat_force?: boolean;
  resultat_ok?: boolean;
  avantage_numerique?: number;
  changement_gardien?: boolean;
  gardien1?: string;
  gardien2?: string;
  type_enreg: string;
  erreur?: boolean;

}

export interface FusilladeRecord extends TypedRecord<FusilladeRecord>, Fusillade {}
export interface Fusillade {
  id: string;
  id_fm: string;
  ronde: number;
  ordre: number;
  id_joueur1: string;
  but1: boolean;
  id_joueur2: string;
  but2: boolean;
}

export const FeuilleMatchEmptyStringToNull = [
  'id_membre',
  'id_equipe',
  'but_special',
  'id_membre',
  'id_membre_passe',
  'id_membre_passe2',
  'id_membre_gardien',
  'gardien1',
  'gardien2',
  'resultat',
  'resultat_adversaire',
  'duree_punition',
  'no_chandail'
];
export const FeuilleMatchZeroToNull = [
  'duree_punition'
];

export const feuilleMatchForInput = val => {
  if (Map.isMap(val)) {
    val = val.toJS();
  } else {
    val = R.clone(val);
  }

  let changed = R.keys(R.pickBy((val, key) => R.contains(key, FeuilleMatchEmptyStringToNull) && val === null,val));
  for (let key of changed) {
    val[key] = '';
  }
  changed = R.keys(R.pickBy((val, key) => R.contains(key, FeuilleMatchZeroToNull) && val === null, val));
  for (let key of changed) {
    val[key] = 0;
  }

  return val;
};

export const feuilleMatchForDb = val => {
  val = R.clone(val);
  let changed = R.keys(R.pickBy((val, key) => R.contains(key, FeuilleMatchEmptyStringToNull) && val === '', val));
  for (let key of changed) {
    val[key] = null;
  }
  changed = R.keys(R.pickBy((val, key) => R.contains(key, FeuilleMatchZeroToNull) && val === 0, val));
  for (let key of changed) {
    val[key] = null;
  }

  return val;
};

/*
  param string[] = liste de champs

  retourne une fonction qui, lorsqu'on lui passe un ev:FeuilleMatch, retourne un objet
  {[fld: string]: val} = liste des valeurs de ev pour les champs fournis, préparés pour utilisation
  dans form
 */
export const getEvValsForInput: (x: string[]) => any = (flds: string[]) =>  R.pipe(
  R.pickAll(flds),
  R.map(val => val === undefined ? null : val),
  feuilleMatchForInput
);

export interface StdPunitionsRecord extends TypedRecord<StdPunitionsRecord>, StdPunitions {
}

export interface StdPunitions {
  id: string;
  description: string;
}

export interface JoueurMatchRecord extends TypedRecord<JoueurMatchRecord>, JoueurMatch {
}

export interface JoueurMatch {
  id: string;
  nom: string;
  id_match: string;
  position: number;
  id_equipe: string;
  no_chandail: string;
}

