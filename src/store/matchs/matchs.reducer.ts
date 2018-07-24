import { fromJS } from 'immutable';
import { IPayloadAction } from '../types';
import { MatchsActions } from './matchs.actions';
import { INITIAL_MATCHS_STATE } from './matchs.initial-state';
import { IMatchsDataRecord } from './matchs.types';

export function MatchsReducer(state: IMatchsDataRecord = INITIAL_MATCHS_STATE,
                              action: IPayloadAction) {
  let payload = fromJS(action.payload);
  switch (action.type) {
    case MatchsActions.MATCHS_MERGE: {
      if (payload.size === 0) {
        return state;
      }
      let res = state.merge(payload);
      if (payload.has('id_saison') && !payload.has('liste')) {
        let saison = res.get('id_saison');
        res = res.set('liste', res.get('liste').filter(match => match.get('id_saison') === saison));
      }
      return res;
    }
    case MatchsActions.MATCHS_TEST:
      console.log('test', action.payload);
      return state;
    default:
      return state;
  }
}
