<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIpkRequest;
use App\Http\Requests\UpdateIpkRequest;
use App\Models\Ipk;
use Illuminate\Support\Facades\Auth;

class IpkController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $ipks = Ipk::forUser($userId)
            ->orderBy('semester')
            ->get();

        $runningSum = 0;
        $runningCount = 0;

        $ipks = $ipks->map(function (Ipk $ipk) use (&$runningSum, &$runningCount) {
            $ipk->computed_running_ipk = null;

            if (! is_null($ipk->ips_actual)) {
                $runningSum += $ipk->ips_actual;
                $runningCount++;
                $ipk->computed_running_ipk = $runningCount ? $runningSum / $runningCount : null;
            }

            return $ipk;
        });

        $cumulativeIpk = $runningCount ? $runningSum / $runningCount : null;
        $latestIps = $ipks->whereNotNull('ips_actual')->last()?->ips_actual;

        return view('ipk.index', compact('ipks', 'cumulativeIpk', 'latestIps'));
    }

    public function create()
    {
        $userId = Auth::id();
        $lastSemester = Ipk::forUser($userId)->whereNotNull('semester')->max('semester');
        $nextSemester = $lastSemester ? ($lastSemester + 1) : 1;

        return view('ipk.create', compact('nextSemester'));
    }

    public function store(StoreIpkRequest $request)
    {
        $payload = $request->payload();
        $payload['user_id'] = Auth::id();

        $ipk = Ipk::create($payload);
        $this->recalculateRunningIpk($ipk->user_id);

        return redirect()->route('ipk.index')->with('success', 'IPS semester berhasil disimpan.');
    }

    public function edit(Ipk $ipk)
    {
        $this->authorizeAccess($ipk);
        return view('ipk.edit', compact('ipk'));
    }

    public function update(UpdateIpkRequest $request, Ipk $ipk)
    {
        $this->authorizeAccess($ipk);

        $payload = $request->payload($ipk);
        $ipk->update($payload);
        $this->recalculateRunningIpk($ipk->user_id);

        return redirect()->route('ipk.index')->with('success', 'IPS semester diperbarui.');
    }

    public function destroy(Ipk $ipk)
    {
        $this->authorizeAccess($ipk);
        $userId = $ipk->user_id;
        $ipk->delete();
        $this->recalculateRunningIpk($userId);

        return redirect()->route('ipk.index')->with('success', 'IPK berhasil dihapus.');
    }

    private function authorizeAccess(Ipk $ipk)
    {
        if ($ipk->user_id !== Auth::id()) {
            abort(403, 'Akses ditolak');
        }
    }

    private function recalculateRunningIpk(int $userId): void
    {
        $ipks = Ipk::forUser($userId)->orderBy('semester')->get();
        $sum = 0;
        $count = 0;

        $ipks->each(function (Ipk $entry) use (&$sum, &$count) {
            if (! is_null($entry->ips_actual)) {
                $sum += $entry->ips_actual;
                $count++;
                $entry->ipk_running = round($sum / $count, 2);
            } else {
                $entry->ipk_running = null;
            }

            $entry->save();
        });
    }
}
