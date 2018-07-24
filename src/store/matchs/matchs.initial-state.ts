import { List, Map } from 'immutable';
import { makeTypedFactory } from 'typed-immutable-record';
import {
  ArenaRecord,
  ClasseRecord,
  CodePunitionRecord, Config, ConfigRecord,
  EquipeRecord,
  IMatchsData,
  IMatchsDataRecord,
  MatchRecord
} from './matchs.types';

export const ConfigFactory = makeTypedFactory<Config, ConfigRecord>(
  {
    duree: 90,
    duree_periodes: 22,
    nb_periodes: 3,
    pts_forfait_gagnant: 1,
    pts_forfait_perdant: 1,
    temps_punitions: List<number>([3, 6, 10]),
    temps_punitions_avantage_num: List<number>([3, 6]),
    temps_punitions_annulees_par_but: 5,
  }
);
export const MatchFactory = makeTypedFactory<IMatchsData, IMatchsDataRecord>(
  {
    liste: Map<string, MatchRecord>(),
    nb_non_charges: 0,
    lieux: Map<string, ArenaRecord>(),
    equipes: Map<string, EquipeRecord>(),
    classes: Map<string, ClasseRecord>(),
    id_saison: '',
    saison: '',
    maj: 0,
    codes_punition: List<CodePunitionRecord>(),
    config: ConfigFactory()
  }
);


export const INITIAL_MATCHS_STATE = MatchFactory();
