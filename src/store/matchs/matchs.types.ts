import { List, Map } from 'immutable';
import { TypedRecord } from 'typed-immutable-record';


export interface IMatchsDataRecord extends TypedRecord<IMatchsDataRecord>, IMatchsData {}
export interface IMatchsData {

  liste: Map<string, MatchRecord>;
  nb_non_charges: number;
  lieux: Map<string, ArenaRecord>;
  equipes: Map<string, EquipeRecord>;
  classes: Map<string, ClasseRecord>;
  id_saison: string;
  saison: string;
  maj: number;
  codes_punition: List<CodePunitionRecord>;
  config: ConfigRecord
}


export interface CodePunitionRecord extends TypedRecord<CodePunitionRecord>, CodePunition {}
export interface CodePunition {
  code: string;
  description: string;
  frequent: boolean;
}

export interface ConfigRecord extends TypedRecord<ConfigRecord>, Config {}
export interface Config {
  duree: number;
  duree_periodes: number;
  nb_periodes: number;
  pts_forfait_gagnant: number;
  pts_forfait_perdant: number;
  temps_punitions: List<number>;
  temps_punitions_avantage_num: List<number>;
  temps_punitions_annulees_par_but: number;
}

export interface MatchRecord extends TypedRecord<MatchRecord>, Match {}
export interface Match {
  id: string;
  id_tournoi: string;
  id_groupe: string;
  date: string;
  debut: string;
  lieu: string;
  id_equipe1: string;
  id_classe1: string;
  id_equipe2: string;
  id_classe2: string;
  id_division: string;
  marqueur: string;
  id_marqueur: string;
  marqueur_confirme: boolean;
  pts1: number;
  pts2: number;
  locked: boolean;
  id_saison: string;
  forfait1: boolean;
  forfait2: boolean;
  sj_ok1: number;
  sj_ok2: number;
}

export interface ArenaRecord extends TypedRecord<ArenaRecord>, Arena {}
export interface Arena {
  id: string;
  description: string;
  id_org: string;
}

export interface EquipeRecord extends TypedRecord<EquipeRecord>, Equipe {}
export interface Equipe {
  id: string;
  nom: string;
  id_classe: string;
}

export interface ClasseRecord extends TypedRecord<ClasseRecord>, Classe {}
export interface Classe {
  id: string;
  classe: string;
  description: string;
  ordre: number;
}