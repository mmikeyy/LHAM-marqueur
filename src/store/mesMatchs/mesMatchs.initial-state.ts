import { makeTypedFactory } from 'typed-immutable-record';
import { MatchRecord } from '../matchs';
import { FeuilleMatch, FeuilleMatchRecord, FusilladeRecord, JoueurMatchRecord } from './mesMatchs.types';
import { MesMatchs, MesMatchsRecord } from './';
import { List, Map } from 'immutable';
import * as R from 'ramda';


export const mesMatchsFactory = makeTypedFactory<MesMatchs, MesMatchsRecord>({
  idMatch: null,
  idMatchCharge: null,
  liste: Map<string, MatchRecord>(),
  nb_non_charges: 0,
  feuilleMatch: List<FeuilleMatchRecord>(),
  joueurs: List<JoueurMatchRecord>(),
  idFMFusilladeChargee: null,
  fusilladeSauvegardee: true,
  fusillade: List<FusilladeRecord>()
});

export const INITIAL_STATE_MES_MATCHS = mesMatchsFactory();

export const evFactory = (vals = {}) => {
  let defaut: FeuilleMatch = {
    id: null,
    id_match: this.matchServ.selectedId,
    id_equipe: null,
    id_membre: null,
    periode: this.periodeChoisie,
    fin_periode: false,
    chrono: '00:00',
    duree_punition: null,
    expulsion: false,
    codes_punition: '',
    but: false,
    but_special: null,
    but_propre_filet: false,
    id_membre_passe: null,
    id_membre_passe2: null,
    passe_propre_filet: false,
    retrait_gardien: false,
    fin_retrait_gardien: false,
    commentaire: null,
    id_membre_gardien: null,
    resultat: 0,
    resultat_adversaire: 0,
    resultat_force: null,
    resultat_ok: false,
    avantage_numerique: 0,
    changement_gardien: false,
    gardien1: null,
    gardien2: null,
    type_enreg: null
  };
  vals = R.pick(R.keys(defaut), vals);
  return R.merge(defaut, vals);
};