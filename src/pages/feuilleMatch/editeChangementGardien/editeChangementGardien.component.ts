import { Component, EventEmitter, OnInit, OnDestroy } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { NavController, NavParams } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../../services/fmService';
import { FeuilleMatch, feuilleMatchForDb, getEvValsForInput } from '../../../store/mesMatchs/mesMatchs.types';
import { IAppState } from '../../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';

@Component(
  {
    selector: '',
    templateUrl: './editeChangementGardien.component.html'
  }
)
export class EditeChangementGardienComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public ev: FeuilleMatch;
  public form: FormGroup;
  public joueurs1;
  public joueurs2;
  public editingChrono: boolean;
  public chrono: string;

  constructor(
    public store: NgRedux<IAppState>,
    public params: NavParams,
    public fb: FormBuilder,
    public fmServ: FmService,
    public navCtrl: NavController
  ) {

  }

  ngOnInit() {
    this.subs.push();
    let gardien = (no) => R.pipe(
      R.values,
      R.filter(R.allPass([R.propEq('id_equipe', this.fmServ.idEquipeNo(no)), R.propEq('position', 2)]))
    )(this.fmServ.joueurs);
    this.joueurs1 = gardien(1);
    this.joueurs2 = gardien(2);
    this.ev = this.params.get('ev');
    this.prep();
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  updateChronoCtrl(val) {
    this.form.controls.chrono.setValue(val);
    this.chrono = val;

  }

  prep() {
    let valeurs = getEvValsForInput([
      'chrono',
      'gardien1',
      'gardien2'
    ])(this.ev);

    this.chrono = this.ev.chrono;

    this.form = this.fb.group(
      {
        chrono: [valeurs.chrono, [Validators.required, Validators.pattern(/^[0-4]\d:\d\d$/)]],
        gardien1: [valeurs.gardien1],
        gardien2: [valeurs.gardien2]
      }
    );
  }

  async soumettre() {
    if (!this.form.valid) {
      return;
    }

    let vals = R.pickBy((v, k) => R.has(k, this.ev) && v != this.ev[k], this.form.value);
    if (R.keys(vals).length === 0) {
      this.navCtrl.pop();
      return;
    }

    vals = R.pipe(
      feuilleMatchForDb,
      R.merge(this.ev)
    )(vals);
    let succes = await this.fmServ.sauvegarderEv(vals);
    if (succes) {
      this.navCtrl.pop();
    }

  }
}
