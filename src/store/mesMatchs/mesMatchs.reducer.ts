import { fromJS, List } from 'immutable';
import { IPayloadAction } from '../types';
import { MesMatchsActions } from './mesMatchs.actions';
import { INITIAL_STATE_MES_MATCHS } from './mesMatchs.initial-state';
import { MesMatchsRecord } from './index';
import { Map } from 'immutable';
import * as R from 'ramda';
import { FeuilleMatchRecord, FusilladeRecord } from './mesMatchs.types';

const sortPer = R.sortWith([R.ascend(R.prop('periode')), R.ascend((val: any) => val.type_enreg === 'fin_periode' ? 1 : 0), R.ascend(R.prop('chrono'))]);

const checkFM = fm => {
  let changed = false;
  let res = fm.withMutations(list => {
    let per = -1;
    let chrono = '00:00';
    let indErr = [];
    list.forEach((ev, ind) => {
      if (ev.get('periode') !== per) {
        per = ev.get('periode');
        chrono = '00:00';
        return;
      }
      if (ev.get('type_enreg') === 'fin_periode') {
        if (chrono > ev.get('chrono')) {
          if (!ev.get('erreur')) {
            indErr.push([ind, true]);
            changed = true;
            return;
          }

        } else {
          if (ev.get('erreur')) {
            indErr.push([ind, false]);
            changed = true;
            return;
          }
        }
      } else {
        chrono = R.max(chrono, ev.get('chrono')) as string;
      }
    });
    indErr.forEach(([ind, val]) => list.setIn([ind, 'erreur'], val));
    return list;
  });

  return changed ? res : fm;
};

export function MesMatchsReducer(state: MesMatchsRecord = INITIAL_STATE_MES_MATCHS,
                                 action: IPayloadAction) {

  switch (action.type) {
    case MesMatchsActions.MESMATCHS_MERGE:
      return state.merge(fromJS(action.payload));
    case MesMatchsActions.MESMATCHS_RESET:
      return INITIAL_STATE_MES_MATCHS;
    case MesMatchsActions.MESMATCHS_SELECT_MATCH:
      return state.set('idMatch', action.payload);
    case MesMatchsActions.MESMATCHS_UPDATE_MATCH:
      if (state.getIn(['liste', action.payload.idMatch])) {
        return state.mergeIn(['liste', action.payload.idMatch], action.payload.data);
      } else {
        return state;
      }
    case MesMatchsActions.MESMATCHS_SAVE_JOUEURS:
      return state.set('joueurs', fromJS(action.payload));
    case MesMatchsActions.MESMATCHS_SAVE_FM: {
      let liste = action.payload;
      if (R.type(liste) === 'Array') {
        console.log('sort liste array', liste);
        liste = sortPer(liste);
        console.log('sorted', liste);
      }
      return state.set('feuilleMatch', fromJS(liste));
    }
    case MesMatchsActions.MESMATCHS_SAVE_JOUEURS_FM: {
      let fm = action.payload.fm;
      if (R.type(fm) === 'Array') {
        console.log('sort liste array', fm);
        fm = sortPer(fm);
        console.log('sorted', fm);
      }
      return state.merge({
        idMatchCharge: action.payload.idMatch,
        joueurs: fromJS(action.payload.joueurs),
        feuilleMatch: fromJS(fm)
      })
    }

    case MesMatchsActions.MESMATCHS_SAVE_EV: {
      let ev = action.payload;
      let newFM;
      let fm = state.get('feuilleMatch');
      let entry = fm.findEntry(event => event.get('id') === ev.id);
      let index;
      let current;
      if (entry) {
        [index, current] = entry;
      }
      let sort = true;
      if (entry) {
        newFM = fm.set(index, fromJS(ev));
        sort = !(ev.periode === current.get('periode') && ev.chrono === current.get('chrono'));
      }
      else {
        newFM = fm.push(fromJS(ev));
      }
      newFM = sort ?
        newFM.sortBy(event => {
          let per = event.get('periode');
          return (per < 10 ? '0' : '') + per + ':' + (event.get('type_enreg') === 'fin_periode' ? 1 : 0) + event.get('chrono')
        })
        :
        newFM;


      let checkedNewFM = checkFM(newFM);


      return state.set('feuilleMatch', checkedNewFM !== newFM ? checkedNewFM : newFM);

    }
    case MesMatchsActions.MESMATCHS_DELETE_EV: {
      let ev = action.payload;
      let id = Map.isMap(ev) ? ev.get('id') : ev.id;
      if (state.get('idFMFusilladeChargee') === id) {
        return state.merge({
          idFMFusilladeChargee: null,
          fusilladeSauvegardee: false,
          fusillade: List(),
          feuilleMatch: state.get('feuilleMatch').filter(ev => ev.get('id') !== id)
        })
      } else {
        return state.set('feuilleMatch', state.get('feuilleMatch').filter(ev => ev.get('id') !== id))
      }

    }
    case MesMatchsActions.MESMATCHS_UPDATE_AN_DN: {
      let changed = false;
      let an = action.payload.an;
      let dn = action.payload.dn;
      let newFM = state.get('feuilleMatch').withMutations(list => {
        let changes = [];

        list.forEach((item, ind) => {
          let current = item.get('avantage_numerique');
          let id = item.get('id');
          let updated = R.contains(id, an) ? 1 : R.contains(id, dn) ? -1 : 0;
          if (updated !== current) {
            changes.push([ind, updated]);
          }
        });
        changes.forEach(chg => {
          list.setIn([chg[0], 'avantage_numerique'], chg[1]);
        });
        changed = changes.length > 0;
      });

      return changed ? state.set('feuilleMatch', newFM) : state;
    }
    case MesMatchsActions.MESMATCHS_UPDATE_RONDE: {
      let ronde = action.payload.ronde;
      let liste = R.map(fromJS, action.payload.liste) as FusilladeRecord[];
      let pos = state.get('fusillade')
        .findLastIndex(el => el.get('ronde') < ronde);
      return state.merge({
          fusillade:
            state
              .get('fusillade')
              .filter(el => el.get('ronde') !== ronde)
              .splice(pos + 1, 0, ...liste),
        fusilladeSauvegardee: false
        }
      );
    }
    case MesMatchsActions.MESMATCHS_CHANGE_BUT: {
      let propJoueur = 'id_joueur' + action.payload.noEq;
      let propBut = 'but' + action.payload.noEq;

      let index = state.get('feuilleMatch').findIndex(item =>
        item.get(propJoueur) === action.payload.idJoueur && item.get('ronde') === action.payload.ronde
      );
      if (index > 0) {
        return state.setIn(['feuilleMatch', index, propBut], action.payload.but);
      } else {
        return state;
      }
    }
    case MesMatchsActions.MESMATCHS_SAVE_FUSILLADE: {
      if (action.payload.id_match !== state.get('idMatchCharge')) {
        console.error('Match ne correspond pas', 'chargé: ' + state.get('idMatchCharge'), 'vs ' + action.payload.id_match);
        alert('Erreur match ne correspond pas');
        return state;
      }
      let evFusillade = state.get('feuilleMatch').find(ev => ev.get('type_enreg') === 'fusillade');
      if (!evFusillade || evFusillade.get('id') !== action.payload.id_fm) {
        console.error('Événement feuille match', (evFusillade ? evFusillade.toJS() : 'introuvable'));
        alert('Événement de feuille de match n\'est pas une fusillade ou ne correspond pas.');
        return state;
      }
      return state.merge({
        fusilladeSauvegardee: true,
        idFMFusilladeChargee: action.payload.id_fm,
        fusillade: fromJS(action.payload.liste)
      })
    }

    default:
      return state;
  }
}
