import {
  ILoginRecord,
  ILogin
} from './login.types';

import { makeTypedFactory } from 'typed-immutable-record';
import { LoginActions } from './login.actions';

export const LoginFactory = makeTypedFactory<ILogin, ILoginRecord>({
  status: LoginActions.LOGIN_DISPLAY_STATUS,
  processing: false,
  pseudo: '',
  adresseMdpPerduEnvoye: '',
  codeValidation: 0
});

export const INITIAL_LOGIN_STATE = LoginFactory();
