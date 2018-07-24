/**
 * Created by micra_000 on 2016-07-03.
 */
import { AbstractControl, Validators, FormControl, ValidatorFn } from '@angular/forms';

export class CustomValidators {
  static noCheckCodePostal = false;

  static validLen(min?: number, max?) {

    let to_ret: ValidatorFn[] = [];

    if (min) {
      to_ret.push(Validators.minLength(min));
    }
    if (max) {
      to_ret.push(Validators.maxLength(max));
    }
    return to_ret;

  }

  static validGenre(ctrl: AbstractControl) {
    if (!ctrl.value || ['M', 'F', 'm', 'f'].indexOf(ctrl.value) === -1) {
      return {genre_invalide: true};
    }
    let initVal = ctrl.value;
    let value = initVal.toUpperCase();
    if (initVal !== value) {
      ctrl.setValue(value);
    }
    return null;
  }

  static validCodePostal(val: AbstractControl) {
    let initVal = val.value;
    if (!val.value || CustomValidators.noCheckCodePostal) {
      CustomValidators.noCheckCodePostal = false;
      return null;
    }
    let value = val.value.toUpperCase();
    if (!/^[A-Z]\d[A-Z] ?\d[A-Z]\d$/.test(value)) {
      return {'code_invalide': true};
    }
    let formattedValue = value.substr(0, 3) + ' ' + value.substr(-3);
    if (initVal !== formattedValue) {
      val.setValue(formattedValue);
    }

    return null;
  }

  static formatCodePostal(val: string) {
    if (!val) {
      return val;
    }
    val = val.toUpperCase();
    if (val.length === 6) {
      val = val.substr(0, 3) + ' ' + val.substr(3);
    }
    return val;
  }

  static validDate(val: AbstractControl) {
    console.log('......validate date ', val.value);
    if (!val.value) {
      console.log('OK');
      return null;
    }
    if (Date.parseExact(val.value, 'yyyy-MM-dd')) {
      console.log('OK');
      return null;
    }
    console.log('INVALID');
    return {date_invalide: true};

  }

  static validCourriel(ctrl: AbstractControl) {
    let val = (ctrl.value || '').trim();

    if (!val || /^(([^<>()\[\]\.,;:\s@\"]+(\.[^<>()\[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i.test(val)) {
      return null;
    }
    return {courriel_invalide: true};
  }

  static validTel(ctrl: FormControl) {
    let val = ctrl.value;
    let initVal = val;
    if (!val) {
      return null;
    }
    val = val.replace(/[^0-9]/g, '');
    if (val.length < 10) {
      return {au_moins_10_chiffres: true};
    }
    let value = `(${val.slice(0, 3)}) ${val.slice(3, 6)}-${val.slice(6, 10)}`;
    if (val.length > 10) {
      value += ' #' + val.substring(10);
    }
    if (initVal !== value) {
      ctrl.setValue(value, {emitEvent: false});
    }

    return null;
  }

  static validLang(ctrl: FormControl) {
    let val = ctrl.value;
    if (!val) {
      return null;
    }
    val = val.toUpperCase();
    if (val !== 'FR' && val !== 'EN') {
      return {langue_inconnue: true};
    }
    return null;
  }

  static formatTel(val) {
    if (!val || val.length < 10) {
      return val;
    }
    val = val.replace(/[^0-9]/g, '');
    let value = `(${val.slice(0, 3)}) ${val.slice(3, 6)}-${val.slice(6, 10)}`;
    if (val.length > 10) {
      value += ' #' + val.substring(10);
    }
    return value;
  }

  static formatTelCtrl(ctrl: FormControl) {
    let val = ctrl.value;
    let formattedVal = CustomValidators.formatTel(val);
    if (formattedVal === val) {
      return;
    }
    ctrl.setValue(formattedVal);
  }

  static rangeInt(min: number, max: number): (FormControl) => Object {
    return (ctrl: FormControl) => {
      // console.log('test ', '[' + ctrl.value + ']; len = ' + ctrl.value.length);
      let val = ctrl.value + '';
      if (!/^([1-9]\d+|\d)/.test(val)) {
        // console.log('invalide', val);
        return {invalide: true};
      }
      let numVal = parseInt(val, 10);
      if (numVal < min) {
        // console.log('petit', numVal, '<', min);
        return {trop_petit: true};
      }
      if (numVal > max) {
        // console.log('grand',numVal, '>', max);
        return {trop_grand: true};
      }
      return null;
    };
  }

  static regex(regex: RegExp): (FormControl) => Object {
    return (ctrl: FormControl) => {
      if (!regex.test(ctrl.value)) {
        return {format_invalide: true};
      }
      return null;
    };

  }

}
