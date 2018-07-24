import { Injectable } from '@angular/core';
import { List } from 'immutable';
import { ToastController } from 'ionic-angular';
import { Observable } from 'rxjs/Observable';
import { GrowlService } from '../services/growl.service';
import { XhrService } from '../services/xhr.service';
import { NgRedux } from '@angular-redux/store';
import { MatchsActions } from '../store/matchs/matchs.actions';
import { MesMatchsActions } from '../store/mesMatchs/mesMatchs.actions';
import { FusilladeRecord } from '../store/mesMatchs/mesMatchs.types';
import { IAppState } from '../store/store';
import { IPayloadAction } from '../store/types';
import * as R from 'ramda';
import * as _ from 'lodash';

@Injectable()
export class MesMatchsEpics {
  constructor(
    public store: NgRedux<IAppState>,
    public xhr: XhrService,
    public growl: GrowlService,
    public toastCtrl: ToastController
  ) {

  }

  getAll(action$: Observable<IPayloadAction>) {
    return action$
      .filter(({type}) => {
        return type === MesMatchsActions.MESMATCHS_GET_ALL
      })
      .mergeMap(({payload}) => {
        return this.xhr.post(
          'gestion_matchs',
          'get_mes_matchs',
          {},
          {observableResult: true}
        )
          .mergeMap((data) => {
              return Observable.of<Object>(
                {
                  type: MatchsActions.MATCHS_MERGE,
                  payload: R.pick([
                    'lieux',
                    'equipes',
                    'classes',
                    'id_saison',
                    'saison'
                  ], data)
                },
                {
                  type: MesMatchsActions.MESMATCHS_MERGE,
                  payload:
                  R.pipe(
                    R.pick([
                      'liste',
                      'nb_non_charges'
                    ]),
                    R.assoc('idFMFusilladeChargee', false),
                    R.assoc('fusilladeSauvegardee', false),
                    R.assoc('fusillade', List<FusilladeRecord>())
                  )(data)

                }
              )
            }
          )
      })

  }

  savePointage(action$: Observable<IPayloadAction>) {
    return action$
      .filter(({type}) => {
        return type === MesMatchsActions.MESMATCHS_SAUVEGARDER_POINTAGE_SRV
      })
      .mergeMap(({payload}) =>
        this.xhr.post(
          'horaires_manuels',
          'sauvegarder_resultats',
          {
            id_match: payload.idMatch,
            pts1: payload.pts1,
            pts2: payload.pts2
          },
          {observableResult: true, noError: true, displayError: true}
        )
          .map(data => {
              console.log('.................', data);
              if (data.result) {
                return {
                  type: MesMatchsActions.MESMATCHS_UPDATE_MATCH,
                  payload: {
                    idMatch: payload.idMatch,
                    data: R.pick(['pts1', 'pts2'], payload)
                  }

                }
              } else {
                return {
                  type: null
                }
              }


            }
          )
      )
  }

  refreshAnDn(action$: Observable<IPayloadAction>) {
    return action$
      .filter(({type}) => {
        return type === MesMatchsActions.MESMATCHS_REFRESH_AN_DN
      })
      .mergeMap(({payload}) =>
        this.xhr.get(
          'marqueur',
          'sauvegarder_an_dn',
          {
            id_match: payload.idMatch
          },
          {observableResult: true, noError: true, displayError: true}
        )
          .map(data => {
              if (data.result) {
                let toast = this.toastCtrl.create({
                  message: `Avantages: ${data.an.length}; Désavantages: ${data.dn.length}`,
                  duration: 2000
                });
                toast.present();
                return {
                  type: MesMatchsActions.MESMATCHS_UPDATE_AN_DN,
                  payload: {
                    idMatch: payload.idMatch,
                    an: data.an,
                    dn: data.dn
                  }

                }
              } else {
                return {
                  type: null
                }
              }


            }
          )
      )
  }

  getFM(action$: Observable<IPayloadAction>) {
    return action$
      .filter(({type}) => {
        return type === MesMatchsActions.MESMATCHS_GET_FUSILLADE
      })
      .mergeMap(({payload}) => {
          if (!payload.idMatch) {
            if (payload.succes) {
              _.defer(payload.succes)
            }
            return Observable.of({
              type: MesMatchsActions.MESMATCHS_MERGE,
              payload: {
                idFusilladeChargee: payload.idFM,
                fusillade: [],
                fusilladeSauvegardee: true
              }
            })
          }
          return this.xhr.get(
            'gestion_feuille_match',
            'get_fusillade',
            {
              id_match: payload.idMatch,
              id_fm: payload.idFM
            },
            {observableResult: true, noError: true, displayError: true}
          )
            .map(data => {
                if (data.result) {
                  if (payload.succes) {
                    _.defer(payload.succes);
                  }
                  return {
                    type: MesMatchsActions.MESMATCHS_MERGE,
                    payload: {
                      idFMFusilladeChargee: payload.idFM,
                      fusillade: data.liste,
                      fusilladeSauvegardee: true
                    }

                  }
                } else {
                  return {
                    type: null
                  }
                }


              }
            )
        }
      )

  }
}