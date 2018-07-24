import { TypedRecord } from 'typed-immutable-record';

export interface ILogin {
  status: string; // indique dialogue ouvert, logged_in, logged_out, etc
  processing: boolean;
  pseudo: string;
  adresseMdpPerduEnvoye: string;
  codeValidation: number;
}

export interface ILoginRecord extends TypedRecord<ILoginRecord>, ILogin {
  
}
