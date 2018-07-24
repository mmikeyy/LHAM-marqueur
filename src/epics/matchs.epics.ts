import { Injectable } from '@angular/core';
import { Observable } from 'rxjs/Observable';
import { GrowlService } from '../services/growl.service';
import { XhrService } from '../services/xhr.service';
import { MatchsActions } from '../store/matchs/matchs.actions';
import { MesMatchsActions } from '../store/mesMatchs/mesMatchs.actions';
import { IAppState } from '../store/store';
import { IPayloadAction } from '../store/types';
import * as R from 'ramda';

import { NgRedux } from '@angular-redux/store';

@Injectable()
export class MatchsEpics {
  constructor(
    public store: NgRedux<IAppState>,
    public xhr: XhrService,
    public growl: GrowlService
  ) {

  }

  getAll(action$: Observable<IPayloadAction>) {
    return action$
      .filter(({type}) => {
        return type === MatchsActions.MATCHS_GET_ALL
      })
      .mergeMap(({payload}) => {
        return this.xhr.get(
          'gestion_matchs',
          'get_all',
          {},
          {observableResult: true}
        )
          .mergeMap((data) => {
              let saisonChanged = !R.propEq('id_saison', this.store.getState().matchs.get('id_saison'), data);

              data.maj = new Date().getTime();
              return Observable.of<Object>(
                {
                  type: MatchsActions.MATCHS_MERGE,
                  payload: R.pick([
                    'liste',
                    'lieux',
                    'equipes',
                    'classes',
                    'id_saison',
                    'saison',
                    'maj',
                    'codes_punition',
                    'config'
                  ], data)
                },
                {
                  type: saisonChanged ? MesMatchsActions.MESMATCHS_RESET : null
                })
            }
          )
      })

  }

  getMore(action$: Observable<IPayloadAction>) {
    return action$
      .filter(({type}) => {
        return type === MatchsActions.MATCHS_GET_MORE
      })
      .mergeMap(({payload}) => {
        let matchs = this.store.getState().matchs;
        if (matchs.get('nb_non_charges') === 0) {
          return {type: 'noop'}
        }

        return this.xhr.post(
          'gestion_matchs',
          'get_more',
          {
            exclure: matchs.keySeq().toJS()
          },
          {observableResult: true}
        )
          .map((data) => {
            return ({
              type: MatchsActions.MATCHS_MERGE,
              payload: R.pick([
                'liste',
                'nb_non_charges'
              ], data)
            })
          })
      })

  }
}