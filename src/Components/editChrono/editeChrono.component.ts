import { Component, EventEmitter, OnInit, OnDestroy, Input, Output } from '@angular/core';
import { Subscription } from 'rxjs';
import { FmService } from '../../services/fmService';
import * as _ from 'lodash';
import * as R from 'ramda';

@Component(
  {
    selector: 'edite-chrono',
    templateUrl: './editeChrono.component.html',
    styles: [
      `div.digit {
            display: inline-block;
            padding: .5rem
        }`,
      `div.digit.selected {
            background-color: #999;
            color: white;
        }`,
      `table.keys {
            margin: auto;
            border-collapse: separate;
            border-spacing: .5em;
        }
      `,
      `table.keys td {
            padding: 2rem;
            font-size: 20px;
            border: 1px solid #ccc;
            background-color: #eee;
            
        }`,
      `table.keys td.inactive {
            opacity: .2
        }
      `,
      `button ion-icon {
            margin-right: 1em
        }`
    ]
  }
)
export class EditeChronoComponent implements OnInit, OnDestroy {

  public digits = [0, 0, 0, 0];
  public rang = 0;


  public subs: Subscription[] = [];
  @Input() public chrono;
  @Output() public chronoChange = new EventEmitter();
  @Input() public display = true;
  @Output() public displayChange = new EventEmitter();
  @Input() public chronoFinPeriode;
  public dureePeriodeSec;
  public valid = true;

  public typeSoumission;

  constructor(
    public fmServ: FmService

  ) {
    console.log('edite chrono');
  }

  ngOnInit() {
    this.subs.push();
    if (!/^\d\d:\d\d$/.test(this.chrono)) {
      this.chrono = '00:00';
    }


    if(!this.chronoFinPeriode) {
      this.chronoFinPeriode = this.fmServ.chronoFinPeriode();
    }
    if (this.chronoFinPeriode && /^\d\d:\d\d$/.test(this.chronoFinPeriode)) {

      this.dureePeriodeSec = this.chronoToSec(this.chronoFinPeriode);
      console.log('duree', this.dureePeriodeSec);
    } else {
      console.log('duree inconnue');
    }
    this.typeSoumission = (this.dureePeriodeSec && this.dureePeriodeSec >= this.chronoToSec(this.chrono)) ? this.fmServ.typeSoumissionChrono : 'normal';

    let chrono = this.chrono;
    if (this.typeSoumission != 'normal') {
      chrono = this.secToChrono(this.dureePeriodeSec - this.chronoToSec(chrono));
    }
    console.log('chrono', chrono);
    this.chronoToDigits(chrono);

    this.validate();
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  next() {
    this.rang = R.min(3, this.rang + 1) as number;
  }
  prev() {
    this.rang = R.max(0, this.rang - 1) as number;
  }

  type(digit) {
    if (this.rang === 2 && digit > 5 || this.rang === 0 && digit > 4) {
      return;
    }
    this.digits[this.rang] = digit;
    this.next();
    this.validate();
  }

  soumettre() {
    let chrono;
    if (this.typeSoumission == 'normal') {
      chrono = this.digitsToChrono();
    } else {
      let normal = this.dureePeriodeSec - this.tempsDigits();
      if (normal > 0) {
        chrono = this.secToChrono(normal);
      } else {
        chrono = this.digitsToChrono();
      }
    }
    this.chronoChange.emit(chrono);

    this.hide();
  }

  hide() {
    this.displayChange.emit(false);
  }

  saveTypeSoumission(val) {
    this.fmServ.typeSoumissionChrono = val;
    this.switchDigits();
  }

  digitsToChrono() {
    return '' + this.digits[0] + this.digits[1] + ':' + this.digits[2] + this.digits[3];
  }
  chronoToDigits(chrono) {
    this.digits = R.pipe(
      R.replace(':', ''),
      R.split(''),
      R.map(digit => parseInt(digit))
    )(chrono)
  }
  chronoToSec(chrono) {
    let matches = chrono.match(/\d\d/g);
    console.log('matches', matches);
    return parseInt(matches[0]) * 60 + parseInt(matches[1]);
  }

  secToChrono(sec) {
    let s = sec % 60;
    let m = (sec - s) / 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  tempsDigits() {
    return this.digits[0] * 600 + this.digits[1] * 60 + this.digits[2] * 10 + this.digits[3];
  }
  tempsToDigits(temps) {

    R.pipe(
      this.secToChrono,
      R.bind(this.chronoToDigits, this as any)
    )(temps);
  }

  validate() {

    this.valid = !this.dureePeriodeSec ? true : this.tempsDigits() <= this.dureePeriodeSec;
  }

  switchDigits() {
    this.tempsToDigits(this.dureePeriodeSec - this.tempsDigits());
  }
}
