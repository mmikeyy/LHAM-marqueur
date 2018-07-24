import { Component, EventEmitter, OnInit, OnDestroy } from '@angular/core';
import { NavController, NavParams } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../../services/fmService';
import { IAppState } from '../../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';

@Component(
  {
    selector: '',
    templateUrl: './editeFinPeriode.component.html',

  }
)
export class EditeFinPeriodeComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public minutes: number;
  public secondes: number;
  public periodeOK: boolean;
  public aucunePeriode: boolean;

  public ev;
  public nouv: number ;
  public minimumSecondes = 0;
  public minimumChrono: string = '';


  constructor(
    public store: NgRedux<IAppState>,
    public fmServ: FmService,
    private navParams: NavParams,
    private navCtrl: NavController
  ) {

  }

  ngOnInit() {
    this.subs.push();

    this.ev = this.navParams.get('ev');
    this.nouv= this.navParams.get('nouv');

    console.log('edite fin periode ', this.ev);
    if (this.ev) {
      let chrono = this.ev.get('chrono');
      this.minutes = parseInt(chrono.match(/^\d+/)) || 22;
      this.secondes = parseInt(chrono.match(/\d+$/) || 0);
      this.periodeOK = true;
      let dernierDePeriode = this.store.getState().mesMatchs.get('feuilleMatch').findLast(ev => ev.get('periode') === this.ev.get('periode') && ev.get('type_enreg') !== 'fin_periode');
      if (dernierDePeriode) {
        let minutes = parseInt(dernierDePeriode.get('chrono').match(/^\d+/));
        let secondes = parseInt(dernierDePeriode.get('chrono').match(/\d+$/));
        this.minimumSecondes = minutes * 60 + secondes;
        this.minimumChrono = Math.floor(minutes) + ':' + (secondes < 10 ? '0' : '') + secondes;
      }
    } else {
      this.minutes = 22;
      this.secondes = 0;

      this.periodeOK = this.periodePrecedenteDefinie(this.nouv);
    }
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  periodePrecedenteDefinie(noPeriode) {
    if (!noPeriode) {
      this.aucunePeriode = true;
      return false;
    }
    return (noPeriode === 1 || this.store.getState().mesMatchs.get('feuilleMatch').find(ev => ev.get('periode') === noPeriode - 1 && ev.get('type_enreg') === 'fin_periode'));

  }

  async soumettre() {
    let chrono = this.minutes + ':' + (this.secondes < 10 ? '0' : '') + this.secondes;
    let periode = (this.ev ? this.ev.get('periode') : this.nouv);
    if (periode) {
      let succes;
      if (this.ev) {
        succes = await this.fmServ.updateEv(this.ev, {chrono});
      } else {
        succes = await this.fmServ.sauvegarderEv({
          type_enreg: 'fin_periode',
          periode,
          id_match: this.fmServ.idMatch,
          chrono
        })
      }

      console.log(succes ? 'OKKKKKKK' : 'EEEchec');
      if  (succes) {
        this.navCtrl.pop();
      }
    }

  }



}
