import { LoginActions } from './login.actions';
import { INITIAL_LOGIN_STATE } from './login.initial-state';
import { ILoginRecord } from './login.types';
import { SessionActions } from '../session/session.actions';
import { IPayloadAction } from '../types';

export function loginReducer(state: ILoginRecord = INITIAL_LOGIN_STATE,
                             action: IPayloadAction) {
  switch (action.type) {
    case 'show':
      console.log(state, 'action', action);
      break;
    case LoginActions.LOGIN_DIAL:
      return state.set('status', LoginActions.LOGIN_DIAL);
    case LoginActions.LOGIN_MDP_PERDU_DIAL:
      return state.set('status', LoginActions.LOGIN_MDP_PERDU_DIAL);
    case LoginActions.LOGIN_NOUVEAU_MDP_DIAL:
      return state.set('status', LoginActions.LOGIN_NOUVEAU_MDP_DIAL);
    case LoginActions.LOGIN_DISPLAY_STATUS:
      return state.merge({status: LoginActions.LOGIN_DISPLAY_STATUS, processing: false});
    case LoginActions.LOGIN_LOGGING_OUT:
      return state.merge({
        status: LoginActions.LOGIN_LOGGING_OUT,
        processing: true
      });
    case LoginActions.LOGIN_LOGOUT_ERROR:
      return state.set('status', LoginActions.LOGIN_LOGOUT_ERROR);
    case LoginActions.LOGIN_SUBMIT:
      // voir epics
      return state.set('processing', true);
    case SessionActions.LOGIN_USER_SUCCESS:
      return state.merge({
        processing: false,
        status: LoginActions.LOGIN_DISPLAY_STATUS,
        pseudo: action.payload.pseudo
      });
    case LoginActions.LOGIN_CHG_DONNEES:
      return state.merge({
        status: LoginActions.LOGIN_CHG_DONNEES
      });
    case LoginActions.LOGIN_PERDU_MDP:
      return state.merge({
        status: LoginActions.LOGIN_PERDU_MDP
      });
    case LoginActions.LOGIN_CODE_MDP_PERDU_ENVOYE:
      let val: { adresseMdpPerduEnvoye: string, codeValidation?: number } = {adresseMdpPerduEnvoye: action.payload.adresse};
      if (action.payload.code !== undefined) {
        val.codeValidation = +action.payload.code;
      }
      return state.merge(val);
    case LoginActions.LOGIN_CHANGER_MDP:
      return state.merge({
        status: LoginActions.LOGIN_CHANGER_MDP
      });
    case LoginActions.LOGIN_CHANGER_PSEUDO:
      return state.set('pseudo', action.payload.pseudo);

    default:
  }
  return state;
}
