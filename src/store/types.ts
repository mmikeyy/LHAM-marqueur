
import { SessionActions } from './session/session.actions';
import { Action } from 'redux';

export interface IPayloadAction extends Action {
  payload?: any;
}

export const ACTION_PROVIDERS = [  SessionActions ];
