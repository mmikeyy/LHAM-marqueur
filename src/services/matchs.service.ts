import { Injectable } from '@angular/core';
import { select } from '../../node_modules/@angular-redux/store/lib/src';
import { Match, MatchRecord } from '../store/matchs';
import { CodePunition } from '../store/matchs/matchs.types';
import { IAppState } from '../store/store';
import { NgRedux } from '@angular-redux/store';
import * as moment from 'moment';
import { Map } from 'immutable';
import * as R from 'ramda';
import { XhrService } from './xhr.service';

export interface MatchInfoJoueur {
  nom: string;
  position: number;
  no_chandail: string;
}

export interface MatchInfo {
  id: string;
  date: string;
  debut: string;
  lieu: string;
  equipe1: string;
  equipe2: string;
  joueurs1: MatchInfoJoueur[];
  joueurs2: MatchInfoJoueur[];
  sj_ok1: number;
  sj_ok2: number;
  fm_ok: boolean;
  locked: boolean;
  forfait1: boolean;
  forfait2: boolean;
  marqueur: string;
  pts1: number;
  pts2: number;

}

@Injectable()
export class MatchsService {

  public matchInfos: {[id:string]: MatchInfo} = {};
  public selectedId: string;

  public tempsPunitions: number[] = [];
  public codesPunitions: CodePunition[] = [];
  public descriptionsPunitions: {[code: string]: string} = {};
  public codesFrequents: string[] = [];

  @select(['matchs', 'config', 'temps_punitions']) $temps_punitions;
  @select(['matchs', 'codes_punition']) $codes_punitions;

  constructor(
    public store: NgRedux<IAppState>,
    public xhr: XhrService
  ) {
    this.$temps_punitions.subscribe(liste => this.tempsPunitions = liste ? liste.toArray() : []);
    this.$codes_punitions.subscribe(liste => {
      if (!liste) {
        console.log('pas de codes punition');
        return;
      }
      this.codesPunitions = liste.toJS();
      this.codesFrequents = [];
      this.descriptionsPunitions = R.pipe(
        R.map((record : CodePunition) => {
          if (record.frequent) {
            this.codesFrequents.push(record.code);
          }
          return [record.code, record.description];
        }),
        R.fromPairs
      )(this.codesPunitions) as {[code: string]: string};

      console.log('codes punitions', this.codesPunitions);
    })
  }

  nomEq(id, state?) { // state = matchs
    state = state || this.store.getState().matchs;
    return state.getIn(['equipes', id, 'nom'], `#${id}`)
  }

  equipesMatch(idMatch?, state?) { // state = mesMatchs
    state = state || this.store.getState().mesMatchs;
    idMatch = idMatch || state.get('idMatch');
    let match: MatchRecord = state.getIn(['liste', idMatch]);
    let idEquipe1 = match.get('id_equipe1');
    let idEquipe2 = match.get('id_equipe2');
    let res = [
      {id: idEquipe1, nom: this.nomEq(idEquipe1)},
      {id: idEquipe2, nom: this.nomEq(idEquipe2)}
    ];
    return res;
  }

  nomArena(id) {
    let state = this.store.getState().matchs;
    return state.getIn(['lieux', id, 'description'], `#${id}`)
  }

  nomPosition(pos) {
    switch (pos) {
      case 0:
        return 'Attaque';
      case 1:
        return 'Défense';
      case 2:
        return 'Gardien';
      case 3:
        return 'Attaque/Défense';
      default:
        return '?';
    }
  }

  matchDate(match: Match | MatchRecord, format = 'dddd DD MMMM hh:mm') {
    if (!match) {
      return '?';
    }
    let dateString;
    if (Map.isMap(match)) {
      dateString = (match as MatchRecord).get('date') + ' ' + (match as MatchRecord).get('debut');
    } else {
      dateString = match.date + ' ' + match.debut;
    }
    return moment(dateString).format(format);
  }

  formatDate(date, heure, format = 'dddd DD MMMM hh:mm') {
    return moment(`${date} ${heure}`).format(format);
  }

  getMatchInfo(id: string, force = false) {
    if (!R.has(id, this.matchInfos)) {
      this.matchInfos[id] = null;
    }
    if (!this.matchInfos[id] || force) {
      this.xhr.post('gestion_matchs', 'get_info_match', {id})
        .then(data => {
          this.matchInfos = R.assoc(id, data.info, this.matchInfos);
        })
    }
  }

  selectMatch(id: string) {
    this.selectedId = id;
    this.getMatchInfo(id);
  }

  matchInfo() {
    return this.matchInfos[this.selectedId];
  }

  descCodePunition(code) {
    return R.propOr('?', code, this.descriptionsPunitions);
  }
  isFrequentCode(code: string) {
    return R.contains(code, this.codesFrequents);
  }
  codeExiste(code: string) {
    return R.has(code, this.descriptionsPunitions);
  }
}