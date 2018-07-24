import { Injectable } from '@angular/core';
import { IAppState } from '../store';
import { NgRedux } from '@angular-redux/store';

@Injectable()
export class MatchsActions {
  static MATCHS_GET_ALL = 'MATCHS_GET_ALL';
  static MATCHS_MERGE = 'MATCHS_MERGE';
  static MATCHS_GET_MORE = 'MATCHS_GET_MORE';
  static MATCHS_TEST = 'MATCHS_TEST';

  constructor(private store: NgRedux<IAppState>) {

  }
  getAll() {
    this.store.dispatch({type: MatchsActions.MATCHS_GET_ALL});
  }
  getMore() {
    this.store.dispatch({type: MatchsActions.MATCHS_GET_MORE});
  }
  merge(data) {
    this.store.dispatch({
      type: MatchsActions.MATCHS_MERGE,
      payload: data
    })
  }
}